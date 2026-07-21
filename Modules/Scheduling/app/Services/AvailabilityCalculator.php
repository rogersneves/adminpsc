<?php

declare(strict_types=1);

namespace Modules\Scheduling\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Psychologists\Enums\AvailabilityType;
use Modules\Psychologists\Models\Psychologist;
use Modules\Psychologists\Models\PsychologistAvailability;
use Modules\Scheduling\Models\Session;

/**
 * Calcula horários disponíveis on-the-fly a partir das regras de disponibilidade do
 * psicólogo + sessões já marcadas — não materializa slots em tabela (docs/06-Roadmap.md
 * Fase 3, decisão de escopo #1).
 *
 * Janelas de "recorrente"/"particular" (adicionam disponibilidade) menos janelas de
 * "bloqueio"/"ferias"/"feriado" (removem), fatiadas em slots de duração+intervalo,
 * menos os horários já ocupados por sessões que ainda bloqueiam o slot.
 *
 * Toda aritmética de intervalo é feita em minutos-desde-a-meia-noite (inteiros), não em
 * objetos Carbon, para manter a subtração de intervalos simples e sem ambiguidade de
 * fuso horário — a data/hora final de cada slot só é montada no fim.
 */
class AvailabilityCalculator
{
    /**
     * @return array<string, array<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>>
     *         chaveado por data (Y-m-d), cada uma com a lista de slots daquele dia.
     */
    public function availableSlots(Psychologist $psychologist, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rules = $psychologist->availabilities()->get();
        $sessionsByDate = $this->bookedSessionsByDate($psychologist, $from, $to);

        $slotsByDate = [];
        $now = CarbonImmutable::now();

        for ($date = $from->startOfDay(); $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $addWindows = $this->windowsForDate($rules, $date, true, $psychologist->default_session_duration_minutes);
            $removeWindows = $this->windowsForDate($rules, $date, false, $psychologist->default_session_duration_minutes);

            $clean = [];
            foreach ($addWindows as $window) {
                $clean = [...$clean, ...$this->subtractWindows($window, $removeWindows)];
            }

            $daySlots = [];
            foreach ($clean as $window) {
                foreach ($this->sliceIntoSlots($window) as $slot) {
                    $startsAt = $date->setTime(intdiv($slot['start'], 60), $slot['start'] % 60);
                    $endsAt = $date->setTime(intdiv($slot['end'], 60), $slot['end'] % 60);

                    if ($startsAt->lessThan($now)) {
                        continue;
                    }

                    if ($this->overlapsBookedSession($startsAt, $endsAt, $sessionsByDate[$date->toDateString()] ?? [])) {
                        continue;
                    }

                    $daySlots[] = ['starts_at' => $startsAt, 'ends_at' => $endsAt];
                }
            }

            if ($daySlots !== []) {
                usort($daySlots, fn ($a, $b) => $a['starts_at'] <=> $b['starts_at']);
                $slotsByDate[$date->toDateString()] = $daySlots;
            }
        }

        return $slotsByDate;
    }

    public function isSlotAvailable(Psychologist $psychologist, CarbonImmutable $startsAt, int $durationMinutes): bool
    {
        $endsAt = $startsAt->addMinutes($durationMinutes);
        $slots = $this->availableSlots($psychologist, $startsAt->startOfDay(), $startsAt->startOfDay());

        foreach ($slots[$startsAt->toDateString()] ?? [] as $slot) {
            if ($slot['starts_at']->equalTo($startsAt) && $slot['ends_at']->equalTo($endsAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{start: int, end: int, duration: int, buffer: int}>
     */
    private function windowsForDate(
        Collection $rules,
        CarbonImmutable $date,
        bool $addsAvailability,
        int $defaultSessionDurationMinutes,
    ): array {
        return $rules
            ->filter(function (PsychologistAvailability $rule) use ($date, $addsAvailability) {
                if ($rule->type->addsAvailability() !== $addsAvailability) {
                    return false;
                }

                return $rule->type->isRecurring()
                    ? $rule->weekday === $date->dayOfWeek
                    : $rule->date?->toDateString() === $date->toDateString();
            })
            ->map(fn (PsychologistAvailability $rule) => [
                'start' => $this->toMinutes($rule->start_time),
                'end' => $this->toMinutes($rule->end_time),
                'duration' => $rule->session_duration_minutes ?? $defaultSessionDurationMinutes,
                'buffer' => $rule->buffer_minutes,
            ])
            ->values()
            ->all();
    }

    /**
     * Subtrai as janelas de "remove" de uma única janela de "add", devolvendo 0+ janelas
     * resultantes (uma janela pode ser cortada ao meio, virando duas).
     *
     * @param  array<int, array{start: int, end: int}>  $removeWindows
     * @return array<int, array{start: int, end: int, duration: int, buffer: int}>
     */
    private function subtractWindows(array $window, array $removeWindows): array
    {
        $segments = [$window];

        foreach ($removeWindows as $remove) {
            $next = [];

            foreach ($segments as $segment) {
                if ($remove['end'] <= $segment['start'] || $remove['start'] >= $segment['end']) {
                    $next[] = $segment;

                    continue;
                }

                if ($remove['start'] > $segment['start']) {
                    $next[] = [...$segment, 'start' => $segment['start'], 'end' => $remove['start']];
                }

                if ($remove['end'] < $segment['end']) {
                    $next[] = [...$segment, 'start' => $remove['end'], 'end' => $segment['end']];
                }
            }

            $segments = $next;
        }

        return array_values(array_filter($segments, fn ($s) => $s['end'] > $s['start']));
    }

    /**
     * @param  array{start: int, end: int, duration: int, buffer: int}  $window
     * @return array<int, array{start: int, end: int}>
     */
    private function sliceIntoSlots(array $window): array
    {
        $slots = [];
        $step = $window['duration'] + $window['buffer'];
        $cursor = $window['start'];

        while ($cursor + $window['duration'] <= $window['end']) {
            $slots[] = ['start' => $cursor, 'end' => $cursor + $window['duration']];
            $cursor += $step;
        }

        return $slots;
    }

    private function toMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return ((int) $hours * 60) + (int) $minutes;
    }

    /**
     * @return array<string, Collection<int, Session>>
     */
    private function bookedSessionsByDate(Psychologist $psychologist, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Session::query()
            ->where('psychologist_id', $psychologist->id)
            ->whereBetween('scheduled_at', [$from->startOfDay(), $to->endOfDay()])
            ->get()
            ->filter(fn (Session $session) => $session->status->blocksSlot())
            ->groupBy(fn (Session $session) => $session->scheduled_at->toDateString())
            ->all();
    }

    private function overlapsBookedSession(CarbonImmutable $startsAt, CarbonImmutable $endsAt, iterable $sessions): bool
    {
        foreach ($sessions as $session) {
            $sessionEnd = $session->scheduled_at->clone()->addMinutes($session->duration_minutes);

            if ($startsAt->lessThan($sessionEnd) && $session->scheduled_at->lessThan($endsAt)) {
                return true;
            }
        }

        return false;
    }
}

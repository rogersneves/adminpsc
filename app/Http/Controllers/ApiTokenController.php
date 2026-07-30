<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestão dos tokens de API pessoais (marco: API pública REST). Emitidos na área
 * autenticada web (sessão + MFA já garantidos), não por um endpoint de login — assim
 * a API não contorna o MFA obrigatório do app. O texto do token só aparece uma vez
 * (no flash logo após criar); depois só fica o hash, como manda o Sanctum.
 */
class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        $tokens = $request->user()->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Api/Tokens', [
            'tokens' => $tokens,
            'newToken' => $request->session()->get('newToken'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $token = $request->user()->createToken($validated['name']);

        return redirect()->route('api-tokens.index')
            ->with('newToken', $token->plainTextToken)
            ->with('status', 'Token criado. Copie agora — ele não será exibido novamente.');
    }

    public function destroy(Request $request, string $tokenId): RedirectResponse
    {
        // Só apaga um token do próprio usuário (nunca de outro).
        $request->user()->tokens()->whereKey($tokenId)->delete();

        return redirect()->route('api-tokens.index')->with('status', 'Token revogado.');
    }
}

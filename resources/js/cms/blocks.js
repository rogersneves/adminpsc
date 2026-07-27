// Blocos customizados do CMS (Fase 8) — os 9 componentes nomeados no roadmap.
//
// Cada bloco carrega estilo inline próprio para renderizar limpo na página pública
// (Blade puro, sem o bundle Tailwind do app). O usuário edita conteúdo/estilo pelo
// GrapesJS; o Style Manager grava as alterações no blob de CSS servido à parte.
//
// Paleta neutra e clean, alinhada ao design system (docs/05).

const PALETTE = {
    ink: '#1f2937',
    muted: '#6b7280',
    brand: '#4f46e5',
    brandInk: '#ffffff',
    soft: '#f9fafb',
    line: '#e5e7eb',
};

const banner = `
<section style="background:${PALETTE.brand};color:${PALETTE.brandInk};padding:14px 24px;text-align:center;font-size:15px;">
  <span>🎉 Bem-vindo à nossa clínica — agende sua primeira sessão hoje.</span>
</section>`;

const hero = `
<section style="padding:72px 24px;text-align:center;background:${PALETTE.soft};">
  <div style="max-width:720px;margin:0 auto;">
    <h1 style="font-size:40px;line-height:1.15;margin:0 0 16px;color:${PALETTE.ink};">Cuidado psicológico acolhedor e profissional</h1>
    <p style="font-size:18px;color:${PALETTE.muted};margin:0 0 28px;">Atendimento humanizado, presencial e online, no seu tempo.</p>
    <a href="#" style="display:inline-block;background:${PALETTE.brand};color:${PALETTE.brandInk};padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:600;">Agendar sessão</a>
  </div>
</section>`;

const cards = `
<section style="padding:56px 24px;">
  <div style="max-width:960px;margin:0 auto;display:grid;grid-template-columns:repeat(3,1fr);gap:20px;">
    <div style="border:1px solid ${PALETTE.line};border-radius:12px;padding:24px;">
      <h3 style="margin:0 0 8px;color:${PALETTE.ink};">Terapia individual</h3>
      <p style="margin:0;color:${PALETTE.muted};font-size:15px;">Acompanhamento focado nas suas necessidades.</p>
    </div>
    <div style="border:1px solid ${PALETTE.line};border-radius:12px;padding:24px;">
      <h3 style="margin:0 0 8px;color:${PALETTE.ink};">Terapia de casal</h3>
      <p style="margin:0;color:${PALETTE.muted};font-size:15px;">Espaço para reconstruir o diálogo.</p>
    </div>
    <div style="border:1px solid ${PALETTE.line};border-radius:12px;padding:24px;">
      <h3 style="margin:0 0 8px;color:${PALETTE.ink};">Atendimento online</h3>
      <p style="margin:0;color:${PALETTE.muted};font-size:15px;">Sessões por vídeo com toda segurança.</p>
    </div>
  </div>
</section>`;

const faq = `
<section style="padding:56px 24px;background:${PALETTE.soft};">
  <div style="max-width:720px;margin:0 auto;">
    <h2 style="text-align:center;color:${PALETTE.ink};margin:0 0 28px;">Perguntas frequentes</h2>
    <div style="border-bottom:1px solid ${PALETTE.line};padding:16px 0;">
      <h3 style="margin:0 0 6px;font-size:17px;color:${PALETTE.ink};">Como agendo minha primeira sessão?</h3>
      <p style="margin:0;color:${PALETTE.muted};font-size:15px;">Cadastre-se e escolha um horário disponível na agenda.</p>
    </div>
    <div style="border-bottom:1px solid ${PALETTE.line};padding:16px 0;">
      <h3 style="margin:0 0 6px;font-size:17px;color:${PALETTE.ink};">Os atendimentos são sigilosos?</h3>
      <p style="margin:0;color:${PALETTE.muted};font-size:15px;">Sim. Seguimos o Código de Ética do CFP e a LGPD.</p>
    </div>
  </div>
</section>`;

const testimonials = `
<section style="padding:56px 24px;">
  <div style="max-width:960px;margin:0 auto;display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">
    <blockquote style="margin:0;border:1px solid ${PALETTE.line};border-radius:12px;padding:24px;">
      <p style="margin:0 0 12px;color:${PALETTE.ink};font-size:16px;">"Encontrei um espaço seguro para cuidar de mim."</p>
      <footer style="color:${PALETTE.muted};font-size:14px;">— Paciente</footer>
    </blockquote>
    <blockquote style="margin:0;border:1px solid ${PALETTE.line};border-radius:12px;padding:24px;">
      <p style="margin:0 0 12px;color:${PALETTE.ink};font-size:16px;">"Atendimento humano do começo ao fim."</p>
      <footer style="color:${PALETTE.muted};font-size:14px;">— Paciente</footer>
    </blockquote>
  </div>
</section>`;

const button = `
<div style="text-align:center;padding:24px;">
  <a href="#" style="display:inline-block;background:${PALETTE.brand};color:${PALETTE.brandInk};padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:600;">Clique aqui</a>
</div>`;

// Formulário: renderização de design. A submissão persistente (captação de lead com
// consentimento LGPD) é deferida para a Fase 10 — o form não envia dados nesta fase.
const form = `
<section style="padding:56px 24px;background:${PALETTE.soft};">
  <form style="max-width:520px;margin:0 auto;display:flex;flex-direction:column;gap:14px;">
    <h2 style="margin:0 0 8px;color:${PALETTE.ink};text-align:center;">Fale conosco</h2>
    <input type="text" placeholder="Seu nome" style="padding:12px 14px;border:1px solid ${PALETTE.line};border-radius:8px;font-size:15px;">
    <input type="email" placeholder="Seu e-mail" style="padding:12px 14px;border:1px solid ${PALETTE.line};border-radius:8px;font-size:15px;">
    <textarea placeholder="Sua mensagem" rows="4" style="padding:12px 14px;border:1px solid ${PALETTE.line};border-radius:8px;font-size:15px;"></textarea>
    <button type="button" style="background:${PALETTE.brand};color:${PALETTE.brandInk};padding:14px;border:none;border-radius:8px;font-weight:600;font-size:15px;cursor:pointer;">Enviar</button>
  </form>
</section>`;

const contact = `
<section style="padding:56px 24px;">
  <div style="max-width:720px;margin:0 auto;text-align:center;">
    <h2 style="margin:0 0 16px;color:${PALETTE.ink};">Entre em contato</h2>
    <p style="margin:0 0 6px;color:${PALETTE.muted};font-size:16px;">📞 (00) 0000-0000</p>
    <p style="margin:0 0 20px;color:${PALETTE.muted};font-size:16px;">✉️ contato@suaclinica.com.br</p>
    <a href="mailto:contato@suaclinica.com.br" style="display:inline-block;background:${PALETTE.brand};color:${PALETTE.brandInk};padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;">Enviar e-mail</a>
  </div>
</section>`;

const footer = `
<footer style="background:${PALETTE.ink};color:#d1d5db;padding:40px 24px;text-align:center;font-size:14px;">
  <p style="margin:0 0 6px;">© Sua Clínica — Todos os direitos reservados.</p>
  <p style="margin:0;">CRP 00/00000 · Política de privacidade</p>
</footer>`;

const BLOCKS = [
    { id: 'psc-banner', label: 'Banner', content: banner },
    { id: 'psc-hero', label: 'Hero', content: hero },
    { id: 'psc-cards', label: 'Cards', content: cards },
    { id: 'psc-faq', label: 'FAQ', content: faq },
    { id: 'psc-testimonials', label: 'Depoimentos', content: testimonials },
    { id: 'psc-button', label: 'Botão', content: button },
    { id: 'psc-form', label: 'Formulário', content: form },
    { id: 'psc-contact', label: 'Contato', content: contact },
    { id: 'psc-footer', label: 'Rodapé', content: footer },
];

export function registerCmsBlocks(editor) {
    const bm = editor.BlockManager;

    BLOCKS.forEach((block) => {
        bm.add(block.id, {
            label: block.label,
            category: 'Componentes da clínica',
            content: block.content,
            attributes: { class: 'gjs-block-section' },
        });
    });
}

/*
 * Marca-texto do AdminPSC. Placeholder tipográfico on-brand (petróleo + sálvia) até
 * o arquivo do logotipo/símbolo ser adicionado ao repositório — quando estiver, troca-se
 * este componente pela imagem. O símbolo "AP" é desenhado em SVG simples com os traços
 * da identidade (linhas suaves, cantos arredondados), não uma cópia do logo final.
 */
export function Brandmark({ compact = false, className = '' }) {
    return (
        <span className={`inline-flex items-center gap-2 ${className}`}>
            <span
                aria-hidden="true"
                className="inline-flex h-8 w-8 items-center justify-center rounded-lg font-bold text-primary-foreground"
                style={{ background: 'var(--brand-petrol)' }}
            >
                AP
            </span>
            {!compact && (
                <span className="text-lg font-extrabold tracking-tight">
                    <span style={{ color: 'var(--brand-petrol)' }}>Admin</span>
                    <span style={{ color: 'var(--brand-sage)' }}>PSC</span>
                </span>
            )}
        </span>
    );
}

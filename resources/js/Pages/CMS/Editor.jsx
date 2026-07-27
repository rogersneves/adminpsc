import { useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import grapesjs from 'grapesjs';
import gjsPresetWebpage from 'grapesjs-preset-webpage';
import 'grapesjs/dist/css/grapes.min.css';
import { buttonVariants } from '@/components/ui/button';
import { registerCmsBlocks } from '@/cms/blocks';

export default function Editor({ page }) {
    const editorRef = useRef(null);
    const containerRef = useRef(null);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});

    const [meta, setMeta] = useState({
        title: page?.title ?? '',
        slug: page?.slug ?? '',
        status: page?.status ?? 'rascunho',
        is_home: page?.is_home ?? false,
        meta_title: page?.meta_title ?? '',
        meta_description: page?.meta_description ?? '',
    });

    useEffect(() => {
        const editor = grapesjs.init({
            container: containerRef.current,
            height: '100%',
            width: 'auto',
            fromElement: false,
            storageManager: false,
            plugins: [gjsPresetWebpage],
            pluginsOpts: {
                [gjsPresetWebpage]: {
                    // Sem edição de HTML/código cru pelo usuário final (requisito da Fase 8).
                    modalImportButton: false,
                    modalImportContent: '',
                    showStylesOnChange: true,
                },
            },
        });

        registerCmsBlocks(editor);

        if (page?.project_data) {
            editor.loadProjectData(page.project_data);
        }

        editorRef.current = editor;

        return () => {
            editor.destroy();
            editorRef.current = null;
        };
    }, []);

    const setField = (key, value) => setMeta((m) => ({ ...m, [key]: value }));

    const save = () => {
        const editor = editorRef.current;
        if (!editor || saving) return;

        setSaving(true);
        setErrors({});

        const payload = {
            ...meta,
            html: editor.getHtml(),
            css: editor.getCss(),
            project_data: editor.getProjectData(),
        };

        const options = {
            preserveScroll: true,
            onError: (e) => setErrors(e),
            onFinish: () => setSaving(false),
        };

        if (page?.id) {
            router.put(`/cms/paginas/${page.id}`, payload, options);
        } else {
            router.post('/cms/paginas', payload, options);
        }
    };

    return (
        <div className="flex h-screen flex-col bg-neutral-100">
            <header className="flex flex-wrap items-center gap-3 border-b bg-white px-4 py-3">
                <Link href="/cms/paginas" className={buttonVariants({ variant: 'ghost', size: 'sm' })}>
                    ← Voltar
                </Link>

                <input
                    type="text"
                    value={meta.title}
                    onChange={(e) => setField('title', e.target.value)}
                    placeholder="Título da página"
                    className="w-48 rounded-md border px-3 py-1.5 text-sm"
                />
                <input
                    type="text"
                    value={meta.slug}
                    onChange={(e) => setField('slug', e.target.value)}
                    placeholder="slug-da-url (opcional)"
                    className="w-44 rounded-md border px-3 py-1.5 text-sm"
                />
                <select
                    value={meta.status}
                    onChange={(e) => setField('status', e.target.value)}
                    className="rounded-md border px-3 py-1.5 text-sm"
                >
                    <option value="rascunho">Rascunho</option>
                    <option value="publicada">Publicada</option>
                </select>
                <label className="flex items-center gap-1.5 text-sm text-neutral-700">
                    <input
                        type="checkbox"
                        checked={meta.is_home}
                        onChange={(e) => setField('is_home', e.target.checked)}
                    />
                    Página inicial
                </label>

                <button
                    type="button"
                    onClick={save}
                    disabled={saving}
                    className={buttonVariants({ size: 'sm' }) + ' ml-auto'}
                >
                    {saving ? 'Salvando…' : 'Salvar'}
                </button>
            </header>

            {Object.keys(errors).length > 0 && (
                <div className="border-b bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">
                    {Object.values(errors).join(' ')}
                </div>
            )}

            <details className="border-b bg-white px-4 py-2 text-sm">
                <summary className="cursor-pointer text-neutral-600">SEO (meta título e descrição)</summary>
                <div className="mt-2 flex flex-wrap gap-3">
                    <input
                        type="text"
                        value={meta.meta_title}
                        onChange={(e) => setField('meta_title', e.target.value)}
                        placeholder="Meta título"
                        className="w-64 rounded-md border px-3 py-1.5 text-sm"
                    />
                    <input
                        type="text"
                        value={meta.meta_description}
                        onChange={(e) => setField('meta_description', e.target.value)}
                        placeholder="Meta descrição"
                        className="w-96 rounded-md border px-3 py-1.5 text-sm"
                    />
                </div>
            </details>

            <div className="min-h-0 flex-1">
                <div ref={containerRef} className="h-full" />
            </div>
        </div>
    );
}

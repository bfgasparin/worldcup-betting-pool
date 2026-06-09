import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import LanguageSelector from '@/components/language-selector';
import { useTranslation } from '@/hooks/use-translation';
import { edit as editLanguage } from '@/routes/language';

export default function Language({
    supportedLocales,
    current,
}: {
    supportedLocales: Record<string, string>;
    current: string | null;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Language settings')} />

            <h1 className="sr-only">{t('Language settings')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Language settings')}
                    description={t('Update the language used across the app')}
                />
                <LanguageSelector
                    supported={supportedLocales}
                    current={current}
                />
            </div>
        </>
    );
}

Language.layout = {
    breadcrumbs: [
        {
            title: 'Language settings',
            href: editLanguage(),
        },
    ],
};

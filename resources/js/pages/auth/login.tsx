import { Form, Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import OtpInput from '@/components/otp-input';
import PasskeyVerify from '@/components/passkey-verify';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { store } from '@/routes/login';
import { send as sendLoginCode } from '@/routes/login/code';

type Props = {
    status?: string;
};

const CODE_LENGTH = 6;

export default function Login({ status }: Props) {
    const { t } = useTranslation();
    const [step, setStep] = useState<'email' | 'code'>('email');
    const [submittedEmail, setSubmittedEmail] = useState('');
    const [otp, setOtp] = useState('');

    const emailForm = useForm({ email: '' });

    const requestCode = ({ advance }: { advance: boolean }): void => {
        emailForm.post(sendLoginCode().url, {
            preserveScroll: true,
            onSuccess: () => {
                setSubmittedEmail(emailForm.data.email);

                if (advance) {
                    setOtp('');
                    setStep('code');
                }
            },
        });
    };

    const submitEmail = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        requestCode({ advance: true });
    };

    const backToEmail = (): void => {
        setStep('email');
        setOtp('');
    };

    return (
        <>
            <Head title={t('Log in')} />

            <PasskeyVerify />

            {step === 'email' ? (
                <form onSubmit={submitEmail} className="flex flex-col gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="email">{t('Email address')}</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="email"
                            placeholder="email@example.com"
                            value={emailForm.data.email}
                            onChange={(event) =>
                                emailForm.setData('email', event.target.value)
                            }
                        />
                        <InputError message={emailForm.errors.email} />
                    </div>

                    <Button
                        type="submit"
                        className="w-full"
                        tabIndex={2}
                        disabled={emailForm.processing}
                        data-test="send-code-button"
                    >
                        {emailForm.processing && <Spinner />}
                        {t('Send login code')}
                    </Button>

                    {status && (
                        <div className="text-center text-sm font-medium text-primary">
                            {status}
                        </div>
                    )}
                </form>
            ) : (
                <Form
                    {...store.form()}
                    resetOnError={['code']}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="email"
                                value={submittedEmail}
                            />

                            <div className="grid gap-2 text-center">
                                <p className="text-sm text-muted-foreground">
                                    {t('We sent a 6-digit code to')}{' '}
                                    <span className="font-medium text-foreground">
                                        {submittedEmail}
                                    </span>
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="code">{t('Login code')}</Label>
                                <OtpInput
                                    name="code"
                                    value={otp}
                                    onChange={setOtp}
                                    length={CODE_LENGTH}
                                    autoFocus
                                    disabled={processing}
                                    invalid={Boolean(
                                        errors.code || errors.email,
                                    )}
                                    aria-label={t('Login code')}
                                />
                                <InputError message={errors.code} />
                                <InputError message={errors.email} />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={
                                    processing || otp.length < CODE_LENGTH
                                }
                                data-test="verify-code-button"
                            >
                                {processing && <Spinner />}
                                {t('Log in')}
                            </Button>

                            <div className="flex flex-col items-center gap-2 text-center text-sm text-muted-foreground">
                                <button
                                    type="button"
                                    onClick={() =>
                                        requestCode({ advance: false })
                                    }
                                    disabled={emailForm.processing}
                                    className="press font-medium text-foreground underline-offset-4 hover:underline disabled:opacity-50"
                                    data-test="resend-code-button"
                                >
                                    {emailForm.processing
                                        ? t('Resending…')
                                        : t('Resend code')}
                                </button>
                                <button
                                    type="button"
                                    onClick={backToEmail}
                                    className="press underline-offset-4 hover:underline"
                                    data-test="change-email-button"
                                >
                                    {t('Use a different email')}
                                </button>
                            </div>

                            {status && (
                                <div className="text-center text-sm font-medium text-primary">
                                    {status}
                                </div>
                            )}
                        </>
                    )}
                </Form>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in',
    description: 'Enter your email to receive a login code',
};

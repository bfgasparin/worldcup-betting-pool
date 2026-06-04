import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Camera, Check, KeyRound, User } from 'lucide-react';
import { useState } from 'react';
import type { ComponentType } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import AvatarUploadField from '@/components/avatar-upload-field';
import InputError from '@/components/input-error';
import OnboardingProgress from '@/components/onboarding/onboarding-progress';
import PasskeyRegistration from '@/components/passkey-register';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useInitials } from '@/hooks/use-initials';
import {
    avatar as avatarRoute,
    complete as completeRoute,
    name as nameRoute,
} from '@/routes/onboarding';
import { edit as profileEdit } from '@/routes/profile';
import { edit as securityEdit } from '@/routes/security';

type Props = {
    hasPasskeys: boolean;
};

const STEPS = [
    { key: 'name', label: 'Your name' },
    { key: 'photo', label: 'Your photo' },
    { key: 'passkey', label: 'Fast sign-in' },
] as const;

type StepKey = (typeof STEPS)[number]['key'];

const STEP_CONTENT: Record<
    StepKey,
    {
        icon: ComponentType<{ className?: string }>;
        title: string;
        blurb: string;
    }
> = {
    name: {
        icon: User,
        title: 'What should we call you?',
        blurb: "This is the name shown on leaderboards and match boards. We've pre-filled what we have on file — confirm it or fix any typos.",
    },
    photo: {
        icon: Camera,
        title: 'Add a profile photo',
        blurb: 'A real photo helps the others recognise you on the leaderboard and across the pool. Totally optional — you can add one anytime.',
    },
    passkey: {
        icon: KeyRound,
        title: 'Set up faster sign-in',
        blurb: 'Create a passkey to sign in with Face ID, your fingerprint or device PIN — no more waiting for a login code next time.',
    },
};

export default function Wizard({ hasPasskeys }: Props) {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    const [step, setStep] = useState<StepKey>('name');
    const currentIndex = STEPS.findIndex((s) => s.key === step);

    const nameForm = useForm({ name: auth.user.name ?? '' });
    const avatarForm = useForm<{ avatar: File | null }>({ avatar: null });
    const [completing, setCompleting] = useState(false);

    const advance = () => {
        if (currentIndex < STEPS.length - 1) {
            setStep(STEPS[currentIndex + 1].key);
        }
    };

    const goBack = () => {
        if (currentIndex > 0) {
            setStep(STEPS[currentIndex - 1].key);
        }
    };

    const saveName = () => {
        nameForm.patch(nameRoute().url, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: advance,
        });
    };

    const saveAvatar = () => {
        if (!avatarForm.data.avatar) {
            advance();

            return;
        }

        avatarForm.post(avatarRoute().url, {
            forceFormData: true,
            preserveState: true,
            preserveScroll: true,
            onSuccess: advance,
        });
    };

    const finish = () => {
        setCompleting(true);
        router.post(
            completeRoute().url,
            {},
            { onError: () => setCompleting(false) },
        );
    };

    const content = STEP_CONTENT[step];
    const StepIcon = content.icon;
    const busy = nameForm.processing || avatarForm.processing || completing;

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background p-6">
            <Head title="Welcome" />

            <div
                aria-hidden
                className="bg-brand-gradient pointer-events-none absolute inset-x-0 -top-40 h-80 opacity-[0.08] blur-3xl"
            />

            <div className="relative w-full max-w-md">
                <div className="mb-7 flex flex-col items-center gap-3 text-center">
                    <div className="app-icon size-12 rounded-2xl shadow-[var(--sh-md)]">
                        <AppLogoIcon className="size-7 text-white" />
                    </div>
                    <div>
                        <p className="font-display text-lg font-semibold tracking-tight">
                            Welcome to the pool
                        </p>
                        <p className="text-sm text-muted-foreground">
                            A few quick things to get you match-ready.
                        </p>
                    </div>
                </div>

                <div className="card-elevated rounded-3xl p-6 sm:p-8">
                    <OnboardingProgress
                        steps={STEPS}
                        currentIndex={currentIndex}
                    />

                    <div
                        key={step}
                        className="mt-7 flex animate-in flex-col gap-5 duration-300 fade-in-0 slide-in-from-bottom-2"
                    >
                        <div className="flex flex-col gap-3">
                            <span className="flex size-11 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <StepIcon className="size-5" />
                            </span>
                            <div className="space-y-1.5">
                                <h1 className="font-display text-xl font-semibold tracking-tight">
                                    {content.title}
                                </h1>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    {content.blurb}
                                </p>
                            </div>
                        </div>

                        {step === 'name' && (
                            <div className="grid gap-2">
                                <Label htmlFor="name">Full name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    autoFocus
                                    autoComplete="name"
                                    placeholder="e.g. Bruno Gasparin"
                                    value={nameForm.data.name}
                                    onChange={(event) =>
                                        nameForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={nameForm.errors.name} />
                            </div>
                        )}

                        {step === 'photo' && (
                            <div className="grid gap-2">
                                <AvatarUploadField
                                    currentUrl={auth.user.avatar}
                                    fallbackInitials={getInitials(
                                        auth.user.name,
                                    )}
                                    file={avatarForm.data.avatar}
                                    disabled={avatarForm.processing}
                                    onSelect={(file) =>
                                        avatarForm.setData('avatar', file)
                                    }
                                />
                                <InputError
                                    className="text-center"
                                    message={avatarForm.errors.avatar}
                                />
                            </div>
                        )}

                        {step === 'passkey' && (
                            <div className="flex flex-col gap-3">
                                {hasPasskeys && (
                                    <p className="flex items-center gap-2 rounded-xl bg-primary/10 px-3 py-2 text-sm font-medium text-primary">
                                        <Check className="size-4" />
                                        You already have a passkey set up.
                                    </p>
                                )}
                                <PasskeyRegistration onSuccess={finish} />
                            </div>
                        )}

                        <p className="text-xs text-muted-foreground">
                            {step === 'passkey' ? (
                                <>
                                    You can add a passkey later in{' '}
                                    <Link
                                        href={securityEdit()}
                                        className="font-medium text-foreground underline-offset-4 hover:underline"
                                    >
                                        Security settings
                                    </Link>
                                    .
                                </>
                            ) : (
                                <>
                                    You can change this anytime in{' '}
                                    <Link
                                        href={profileEdit()}
                                        className="font-medium text-foreground underline-offset-4 hover:underline"
                                    >
                                        Profile settings
                                    </Link>
                                    .
                                </>
                            )}
                        </p>
                    </div>

                    <div className="mt-8 flex items-center justify-between gap-3">
                        {currentIndex > 0 ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={busy}
                                onClick={goBack}
                            >
                                <ArrowLeft className="size-4" />
                                Back
                            </Button>
                        ) : (
                            <span />
                        )}

                        <div className="flex items-center gap-1">
                            {step !== 'passkey' && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={busy}
                                    onClick={advance}
                                >
                                    Skip for now
                                </Button>
                            )}

                            {step === 'name' && (
                                <Button
                                    type="button"
                                    disabled={busy}
                                    onClick={saveName}
                                >
                                    {nameForm.processing && <Spinner />}
                                    Continue
                                </Button>
                            )}

                            {step === 'photo' && (
                                <Button
                                    type="button"
                                    disabled={busy}
                                    onClick={saveAvatar}
                                >
                                    {avatarForm.processing && <Spinner />}
                                    Continue
                                </Button>
                            )}

                            {step === 'passkey' && (
                                <Button
                                    type="button"
                                    disabled={busy}
                                    onClick={finish}
                                >
                                    {completing && <Spinner />}
                                    Finish
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

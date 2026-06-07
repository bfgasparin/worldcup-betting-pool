import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Camera, Check, KeyRound, User } from 'lucide-react';
import { useState } from 'react';
import type { ComponentType } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import AvatarUploadField from '@/components/avatar-upload-field';
import InputError from '@/components/input-error';
import OnboardingProgress from '@/components/onboarding/onboarding-progress';
import OnboardingStepper from '@/components/onboarding/onboarding-stepper';
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
        blurb: 'A real photo helps the others recognise you on leaderboards and in every pool you join. Totally optional — you can add one anytime.',
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
        <div className="grid min-h-svh lg:grid-cols-2">
            <Head title="Welcome" />

            {/* Pitch brand + progress panel — desktop only. */}
            <aside className="hero relative hidden flex-col justify-between overflow-hidden p-10 lg:flex xl:p-14">
                <div className="hero-lines" />
                <div className="pointer-events-none absolute inset-0 -z-10">
                    <div className="absolute -top-24 -right-28 size-[34rem] rounded-full bg-primary/25 blur-[120px]" />
                    <div className="absolute -bottom-40 -left-32 size-[26rem] rounded-full bg-accent/15 blur-[120px]" />
                </div>

                <span className="relative z-10 flex w-fit items-center gap-3">
                    <span className="app-icon size-10 rounded-2xl shadow-[var(--sh-sm)]">
                        <AppLogoIcon className="size-6 text-white" />
                    </span>
                    <span className="inline-flex items-baseline font-display text-lg font-semibold tracking-tight text-foreground">
                        Brothers
                        <span className="ml-2 text-[10px] font-bold tracking-[0.22em] text-amber uppercase">
                            Bets
                        </span>
                    </span>
                </span>

                <div className="relative z-10 flex flex-col gap-8">
                    <div className="flex flex-col gap-3">
                        <span className="inline-flex w-fit items-center gap-2.5 rounded-full bg-muted px-4 py-1.5 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase">
                            <span className="bg-brand-gradient size-2 rounded-full" />
                            Getting started
                        </span>
                        <h2 className="max-w-sm font-display text-4xl font-semibold tracking-tight text-balance text-foreground">
                            Let&apos;s get you{' '}
                            <span className="text-primary">match-ready</span>.
                        </h2>
                        <p className="max-w-sm text-pretty text-muted-foreground">
                            A few quick things and you&apos;re in — then
                            it&apos;s straight to the predictions.
                        </p>
                    </div>

                    <OnboardingStepper
                        steps={STEPS}
                        currentIndex={currentIndex}
                    />
                </div>

                <p className="relative z-10 max-w-sm text-sm text-muted-foreground">
                    Every step is optional — you can change all of this later in
                    settings.
                </p>
            </aside>

            {/* Working area — full screen on mobile. */}
            <div className="flex min-h-svh flex-col bg-background">
                {/* Slim branded header + progress — mobile only. */}
                <div className="hero relative overflow-hidden px-6 pt-8 pb-5 lg:hidden">
                    <div className="hero-lines" />
                    <div className="relative flex flex-col gap-4">
                        <span className="flex w-fit items-center gap-2.5">
                            <span className="app-icon size-9 rounded-xl shadow-[var(--sh-sm)]">
                                <AppLogoIcon className="size-5 text-white" />
                            </span>
                            <span className="inline-flex items-baseline font-display text-base font-semibold tracking-tight text-foreground">
                                Brothers
                                <span className="ml-2 text-[10px] font-bold tracking-[0.22em] text-amber uppercase">
                                    Bets
                                </span>
                            </span>
                        </span>
                        <OnboardingProgress
                            steps={STEPS}
                            currentIndex={currentIndex}
                        />
                    </div>
                </div>

                {/* Active step. */}
                <div className="flex flex-1 flex-col justify-center px-6 py-8 sm:px-10">
                    <div
                        key={step}
                        className="mx-auto flex w-full max-w-md animate-in flex-col gap-6 duration-300 fade-in-0 slide-in-from-bottom-2"
                    >
                        <div className="flex flex-col gap-3">
                            <span className="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <StepIcon className="size-6" />
                            </span>
                            <div className="space-y-2">
                                <h1 className="font-display text-2xl font-semibold tracking-tight">
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
                </div>

                {/* Sticky action bar — always within thumb's reach on mobile. */}
                <div className="sticky bottom-0 border-t border-border bg-background/90 px-6 py-4 pb-[calc(1rem+env(safe-area-inset-bottom,0px))] backdrop-blur sm:px-10">
                    <div className="mx-auto flex w-full max-w-md items-center justify-between gap-3">
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

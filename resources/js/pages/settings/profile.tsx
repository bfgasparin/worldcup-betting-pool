import { Form, Head, router, useForm, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import AvatarUploadField from '@/components/avatar-upload-field';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/hooks/use-initials';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

function ProfilePhoto({ user }: { user: Auth['user'] }) {
    const getInitials = useInitials();
    const form = useForm<{ avatar: File | null }>({ avatar: null });

    const save = () => {
        if (!form.data.avatar) {
            return;
        }

        form.post(ProfileController.updateAvatar().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('avatar'),
        });
    };

    const remove = () => {
        router.delete(ProfileController.destroyAvatar().url, {
            preserveScroll: true,
        });
    };

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Photo"
                description="Help others recognise you on the leaderboard"
            />

            <div className="flex flex-col items-start gap-4">
                <AvatarUploadField
                    currentUrl={user.avatar}
                    fallbackInitials={getInitials(user.name)}
                    file={form.data.avatar}
                    disabled={form.processing}
                    onSelect={(file) => form.setData('avatar', file)}
                />

                <InputError message={form.errors.avatar} />

                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        disabled={form.processing || !form.data.avatar}
                        onClick={save}
                    >
                        Save photo
                    </Button>

                    {user.avatar && (
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={form.processing}
                            onClick={remove}
                        >
                            Remove photo
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function Profile() {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-12">
                <ProfilePhoto user={auth.user} />

                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Profile"
                        description="Update your name and email address"
                    />

                    <Form
                        {...ProfileController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>

                                    <Input
                                        id="name"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.name}
                                        name="name"
                                        required
                                        autoComplete="name"
                                        placeholder="Full name"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder="Email address"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.email}
                                    />
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="update-profile-button"
                                    >
                                        Save
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};

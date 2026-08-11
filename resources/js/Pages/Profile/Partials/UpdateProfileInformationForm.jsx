import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import TextInput from "@/Components/TextInput";
import { Transition } from "@headlessui/react";
import { Link, useForm, usePage } from "@inertiajs/react";

export default function UpdateProfileInformationForm({
    mustVerifyEmail,
    status,
    className = "",
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name || "",
            email: user.email || "",
            phone: user.phone || "",
        });

    const submit = (e) => {
        e.preventDefault();

        patch(route("profile.update"), {
            preserveScroll: true,
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-semibold text-slate-900">
                    Personal Information
                </h2>

                <p className="mt-1 text-sm text-slate-500">
                    Update your display name, email address and phone number.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-5">
                <div className="grid gap-5 md:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="name" value="Display Name" />

                        <TextInput
                            id="name"
                            className="mt-1 block w-full"
                            value={data.name}
                            onChange={(e) => setData("name", e.target.value)}
                            required
                            autoComplete="name"
                        />

                        <InputError className="mt-2" message={errors.name} />
                    </div>

                    <div>
                        <InputLabel htmlFor="phone" value="Phone Number" />

                        <TextInput
                            id="phone"
                            type="tel"
                            className="mt-1 block w-full"
                            value={data.phone}
                            onChange={(e) => setData("phone", e.target.value)}
                            autoComplete="tel"
                            placeholder="+91 98765 43210"
                        />

                        <InputError className="mt-2" message={errors.phone} />
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email Address" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData("email", e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p className="text-sm text-amber-800">
                            Your email address is unverified.{" "}
                            <Link
                                href={route("verification.send")}
                                method="post"
                                as="button"
                                className="font-semibold underline"
                            >
                                Re-send verification email
                            </Link>
                        </p>

                        {status === "verification-link-sent" && (
                            <p className="mt-2 text-sm font-medium text-green-600">
                                A new verification link has been sent to your
                                email address.
                            </p>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4 border-t border-slate-100 pt-5">
                    <PrimaryButton disabled={processing}>
                        {processing ? "Saving..." : "Save Changes"}
                    </PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm font-medium text-green-600">
                            Profile updated successfully.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}

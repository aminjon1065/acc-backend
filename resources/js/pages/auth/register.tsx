import { Head } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import AuthLayout from '@/layouts/auth-layout';

export default function Register() {
    router.visit('/', { method: 'get', replace: true });

    return (
        <AuthLayout
            title="Registration Disabled"
            description="Self-registration is disabled. Please contact your administrator."
        >
            <Head title="Registration Disabled" />
            <p className="text-center text-muted-foreground">
                Contact your administrator to create an account.
            </p>
        </AuthLayout>
    );
}

// resources/js/components/app-logo.tsx
export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                <svg width="18" height="18" viewBox="0 0 32 32" fill="none">
                    <path
                        d="M8 16C8 11.6 11.6 8 16 8s8 3.6 8 8-3.6 8-8 8"
                        stroke="currentColor"
                        strokeWidth="2.5"
                        strokeLinecap="round"
                    />
                    <path
                        d="M12 16h8M16 12v8"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                    />
                </svg>
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate font-bold leading-tight">CK Accounting</span>
                <span className="truncate text-xs text-sidebar-foreground/40 font-medium">Admin Panel</span>
            </div>
        </>
    );
}

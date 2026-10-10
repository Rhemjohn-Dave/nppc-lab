import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-full ring-2 ring-nppc-900/20">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-full bg-slate-800 text-xs font-medium text-white">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid min-w-0 flex-1 text-left text-xs leading-tight">
                <span className="truncate font-semibold text-slate-800">
                    {user.name}
                </span>
                {showEmail ? (
                    <span className="truncate font-mono text-[10px] text-slate-500">
                        {user.email}
                    </span>
                ) : null}
            </div>
        </>
    );
}

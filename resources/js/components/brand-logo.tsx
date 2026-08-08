
import { cn } from '@/lib/utils';


export function BrandLogo({ className }: { className?: string }) {
    return (
       
         <img
                src="/Brand.png"
                alt="Logo Tarombo Batak"
                className={cn('size-full object-contain', className)}
            />
    );
}

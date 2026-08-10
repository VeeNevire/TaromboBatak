import * as React from 'react';
import { cn } from '@/lib/utils';

type TabsContextValue = {
    value: string;
    onValueChange: (value: string) => void;
};

const TabsContext = React.createContext<TabsContextValue | undefined>(undefined);

function useTabsContext() {
    const context = React.useContext(TabsContext);
    if (!context) {
        throw new Error('Tabs components must be used within <Tabs>');
    }
    return context;
}

interface TabsProps {
    defaultValue: string;
    value?: string;
    onValueChange?: (value: string) => void;
    children: React.ReactNode;
    className?: string;
}

export function Tabs({ defaultValue, value: controlledValue, onValueChange, children, className }: TabsProps) {
    const [internalValue, setInternalValue] = React.useState(defaultValue);
    const value = controlledValue ?? internalValue;

    const handleValueChange = (newValue: string) => {
        if (controlledValue === undefined) {
            setInternalValue(newValue);
        }
        onValueChange?.(newValue);
    };

    return (
        <TabsContext.Provider value={{ value, onValueChange: handleValueChange }}>
            <div className={className}>{children}</div>
        </TabsContext.Provider>
    );
}

interface TabsListProps {
    children: React.ReactNode;
    className?: string;
}

export function TabsList({ children, className }: TabsListProps) {
    return (
        <div
            className={cn(
                'inline-flex h-10 items-center justify-center rounded-md bg-tb-surface-container p-1 text-tb-on-surface-variant',
                className,
            )}
        >
            {children}
        </div>
    );
}

interface TabsTriggerProps {
    value: string;
    children: React.ReactNode;
    className?: string;
}

export function TabsTrigger({ value: triggerValue, children, className }: TabsTriggerProps) {
    const { value, onValueChange } = useTabsContext();
    const isActive = value === triggerValue;

    return (
        <button
            type="button"
            onClick={() => onValueChange(triggerValue)}
            className={cn(
                'inline-flex items-center justify-center whitespace-nowrap rounded-sm px-3 py-1.5 text-sm font-medium ring-offset-tb-surface transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-tb-primary focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
                isActive
                    ? 'bg-tb-surface-bright text-tb-on-surface shadow-sm'
                    : 'text-tb-on-surface-variant hover:text-tb-on-surface',
                className,
            )}
        >
            {children}
        </button>
    );
}

interface TabsContentProps {
    value: string;
    children: React.ReactNode;
    className?: string;
}

export function TabsContent({ value: contentValue, children, className }: TabsContentProps) {
    const { value } = useTabsContext();

    if (value !== contentValue) {
        return null;
    }

    return (
        <div
            className={cn(
                'mt-2 ring-offset-tb-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-tb-primary focus-visible:ring-offset-2',
                className,
            )}
        >
            {children}
        </div>
    );
}

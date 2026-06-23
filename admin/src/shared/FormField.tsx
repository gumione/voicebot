import { type ReactNode, type InputHTMLAttributes, type TextareaHTMLAttributes, forwardRef } from 'react';

interface FormFieldProps {
    label: string;
    error?: string;
    required?: boolean;
    hint?: ReactNode;
    children?: ReactNode;
}

export const FormField = ({ label, error, required, hint, children }: FormFieldProps) => (
    <div>
        <label className="block text-xs font-medium text-secondary mb-1.5">
            {label}{required && ' *'}
        </label>
        {children}
        {hint && <p className="text-secondary/70 text-xs mt-1">{hint}</p>}
        {error && <p className="text-error text-xs mt-1">{error}</p>}
    </div>
);

interface FormInputProps extends InputHTMLAttributes<HTMLInputElement> {
    error?: string;
}

export const FormInput = forwardRef<HTMLInputElement, FormInputProps>(
    ({ error, className = '', ...props }, ref) => (
        <input
            ref={ref}
            className={`w-full px-3.5 py-2.5 border rounded-lg text-sm transition-colors ${
                error ? 'border-error focus:ring-error/20' : 'border-border'
            } ${className}`}
            {...props}
        />
    )
);

interface FormTextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    error?: string;
}

export const FormTextarea = forwardRef<HTMLTextAreaElement, FormTextareaProps>(
    ({ error, className = '', ...props }, ref) => (
        <textarea
            ref={ref}
            className={`w-full px-3.5 py-2.5 border rounded-lg text-sm resize-y transition-colors ${
                error ? 'border-error focus:ring-error/20' : 'border-border'
            } ${className}`}
            {...props}
        />
    )
);

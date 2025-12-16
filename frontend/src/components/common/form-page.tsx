"use client"

import { ArrowLeft } from "lucide-react"
import { useNavigate } from "react-router"
import { useTranslation } from "react-i18next"

import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { Input } from "@/components/ui/input"
import { cn } from "@/lib/utils"

interface FormHeaderProps {
  title: string
  description?: string
  backUrl: string
}

export function FormHeader({ title, description, backUrl }: FormHeaderProps) {
  const navigate = useNavigate()
  const { t } = useTranslation()

  return (
    <div className="space-y-2 pb-2">
      <Button
        variant="ghost"
        size="sm"
        onClick={() => navigate(backUrl)}
        className="-ml-2 h-8 text-muted-foreground hover:text-foreground"
      >
        <ArrowLeft className="mr-1.5 h-4 w-4" />
        {t('common.back')}
      </Button>
      <div className="space-y-0.5">
        <h1 className="text-xl font-semibold tracking-tight text-foreground">{title}</h1>
        {description && (
          <p className="text-sm text-muted-foreground">{description}</p>
        )}
      </div>
    </div>
  )
}

interface FormSectionProps {
  title: string
  description?: string
  children: React.ReactNode
}

export function FormSection({ title, description, children }: FormSectionProps) {
  return (
    <div className="rounded-lg bg-muted/30 p-4">
      <div className="mb-4">
        <h3 className="text-sm font-medium text-foreground">{title}</h3>
        {description && <p className="text-xs text-muted-foreground mt-0.5">{description}</p>}
      </div>
      <div className="space-y-4">{children}</div>
    </div>
  )
}

interface FormFieldProps {
  label: string
  required?: boolean
  error?: string
  children: React.ReactNode
  className?: string
}

export function FormField({
  label,
  required,
  error,
  children,
  className,
}: FormFieldProps) {
  return (
    <div className={cn("space-y-1.5", className)}>
      <Label className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
        {label}
        {required && <span className="text-destructive">*</span>}
      </Label>
      {children}
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  )
}

interface FormActionsProps {
  onSave: () => void
  onCancel: () => void
  loading?: boolean
  saveLabel?: string
  cancelLabel?: string
}

export function FormActions({
  onSave,
  onCancel,
  loading,
  saveLabel,
  cancelLabel,
}: FormActionsProps) {
  const { t } = useTranslation()

  return (
    <div className="flex justify-end gap-2 pt-4">
      <Button variant="outline" size="sm" className="h-8" onClick={onCancel} disabled={loading}>
        {cancelLabel || t('common.cancel')}
      </Button>
      <Button size="sm" className="h-8" onClick={onSave} disabled={loading}>
        {loading ? t('common.loading') : saveLabel || t('common.save')}
      </Button>
    </div>
  )
}

interface FormInputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  error?: boolean
}

export function FormInput({ error, className, ...props }: FormInputProps) {
  return (
    <Input
      className={cn(
        "h-9 bg-background",
        error && "border-destructive focus-visible:ring-destructive",
        className
      )}
      {...props}
    />
  )
}
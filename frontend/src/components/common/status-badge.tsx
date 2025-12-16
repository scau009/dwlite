"use client"

import { cn } from "@/lib/utils"

export type StatusType = 'success' | 'warning' | 'error' | 'info' | 'default'

interface StatusBadgeProps {
  status: StatusType
  label: string
  className?: string
}

export function StatusBadge({ status, label, className }: StatusBadgeProps) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium",
        "transition-colors",
        {
          "bg-status-success text-status-success-foreground": status === 'success',
          "bg-status-warning text-status-warning-foreground": status === 'warning',
          "bg-status-error text-status-error-foreground": status === 'error',
          "bg-status-info text-status-info-foreground": status === 'info',
          "bg-status-default text-status-default-foreground": status === 'default',
        },
        className
      )}
    >
      {label}
    </span>
  )
}
import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "flex h-9 w-full min-w-0 rounded-sm border border-line-control bg-surface px-3 py-1 text-base text-ink placeholder:text-ink-muted selection:bg-primary selection:text-on-primary read-only:bg-surface-sunken disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
        "aria-invalid:border-danger aria-invalid:bg-danger-soft",
        className
      )}
      {...props}
    />
  )
}

export { Input }

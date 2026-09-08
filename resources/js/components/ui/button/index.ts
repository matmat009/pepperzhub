import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex shrink-0 select-none items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-transparent text-sm font-medium outline-none transition-[color,background-color,border-color,box-shadow,transform] duration-150 ease-out active:scale-[0.97] active:shadow-none motion-reduce:active:scale-100 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-45 disabled:shadow-none data-[loading=true]:cursor-wait data-[loading=true]:opacity-70 focus-visible:ring-3 focus-visible:ring-ring/30 focus-visible:ring-offset-2 focus-visible:ring-offset-background aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
  {
    variants: {
      variant: {
        default:
          "border-primary bg-primary text-primary-foreground shadow-xs hover:border-primary/90 hover:bg-primary/90 focus-visible:ring-primary/35",
        destructive:
          "border-destructive/60 bg-background text-destructive shadow-xs hover:border-destructive hover:bg-destructive/10 hover:text-destructive focus-visible:ring-destructive/25 active:bg-destructive/15 dark:border-destructive/70 dark:bg-destructive/5 dark:hover:bg-destructive/15 dark:focus-visible:ring-destructive/35",
        outline:
          "border-muted-foreground/35 bg-background text-foreground shadow-xs hover:border-primary/45 hover:bg-accent hover:text-accent-foreground active:bg-accent/80 dark:border-muted-foreground/45 dark:hover:border-primary/55 dark:hover:bg-accent",
        secondary:
          "border-muted-foreground/25 bg-secondary text-secondary-foreground shadow-xs hover:border-primary/35 hover:bg-secondary/80 active:bg-secondary/70 dark:border-muted-foreground/35 dark:hover:border-primary/45",
        ghost:
          "border-transparent bg-transparent text-muted-foreground shadow-none hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/70",
        link: "h-auto border-transparent bg-transparent p-0 text-primary shadow-none underline-offset-4 hover:underline active:scale-100",
      },
      size: {
        "default": "h-10 px-4 has-[>svg]:px-3.5",
        "xs": "h-7 gap-1 px-2.5 text-xs has-[>svg]:px-2 [&_svg:not([class*='size-'])]:size-3",
        "sm": "h-9 gap-1.5 px-3.5 has-[>svg]:px-3",
        "lg": "h-11 px-6 has-[>svg]:px-5",
        "icon": "size-10",
        "icon-xs": "size-7 [&_svg:not([class*='size-'])]:size-3",
        "icon-sm": "size-9",
        "icon-lg": "size-11",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>

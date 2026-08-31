import { useEffect, useRef } from "react";

export function useModalLayer(active: string | null) {
  const restoreFocus = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!active) return;

    const layer = document.querySelector<HTMLElement>(
      ".dialog-backdrop .dialog, .drawer",
    );
    const shell = document.querySelector<HTMLElement>(".app-shell");
    if (!layer || !shell) return;

    restoreFocus.current =
      document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;

    const background = Array.from(shell.children).filter(
      (element) =>
        !element.classList.contains("dialog-backdrop") &&
        !element.classList.contains("drawer") &&
        !element.classList.contains("drawer-backdrop"),
    ) as HTMLElement[];

    background.forEach((element) => {
      element.inert = true;
    });

    const focusable = () =>
      Array.from(
        layer.querySelectorAll<HTMLElement>(
          'button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
        ),
      );
    const initial =
      layer.querySelector<HTMLElement>(
        "[data-dialog-initial-focus], [autofocus]",
      ) ??
      focusable()[0] ??
      layer;

    window.requestAnimationFrame(() => initial.focus());

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        const close = layer.querySelector<HTMLButtonElement>(
          'button[aria-label^="ปิด"]',
        );
        if (close && !close.disabled) {
          event.preventDefault();
          close.click();
        }
        return;
      }

      if (event.key !== "Tab") return;
      const controls = focusable();
      if (controls.length === 0) {
        event.preventDefault();
        layer.focus();
        return;
      }

      const first = controls[0];
      const last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    document.addEventListener("keydown", onKeyDown);
    return () => {
      document.removeEventListener("keydown", onKeyDown);
      background.forEach((element) => {
        element.inert = false;
      });
      if (restoreFocus.current?.isConnected) restoreFocus.current.focus();
    };
  }, [active]);
}

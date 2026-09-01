import { useEffect, useRef } from "react";

export function useModalLayer(active: string | null) {
  const restoreFocus = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!active) return;

    const layers = document.querySelectorAll<HTMLElement>(
      ".dialog-backdrop .dialog, .drawer",
    );
    const layer = layers.item(layers.length - 1);
    const shell = document.querySelector<HTMLElement>(".app-shell");
    if (!layer || !shell) return;

    restoreFocus.current =
      document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;

    const modalRoot =
      layer.closest<HTMLElement>(".dialog-backdrop, .drawer") ?? layer;
    const background: Array<{ element: HTMLElement; wasInert: boolean }> = [];
    let branch: HTMLElement | null = modalRoot;

    while (branch && branch !== shell && branch.parentElement) {
      const parent: HTMLElement = branch.parentElement;
      for (const sibling of Array.from(parent.children)) {
        if (sibling === branch || !(sibling instanceof HTMLElement)) continue;
        background.push({ element: sibling, wasInert: sibling.inert });
        sibling.inert = true;
      }
      branch = parent;
    }

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
      background.forEach(({ element, wasInert }) => {
        element.inert = wasInert;
      });
      if (restoreFocus.current?.isConnected) restoreFocus.current.focus();
    };
  }, [active]);
}

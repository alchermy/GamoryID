import { useEffect, useRef, type CSSProperties } from "react";
import { CalendarCheck2, TrendingUp } from "lucide-react";
import { AppInventoryMock } from "./AppInventoryMock";
import { Mascot } from "./Mascot";
import { Sparkline } from "./mocks";

const reducedMotion = () =>
  typeof window !== "undefined" &&
  (window.matchMedia("(prefers-reduced-motion: reduce)").matches ||
    window.matchMedia("(pointer: coarse)").matches);

/**
 * Hero showpiece: a tilted GamoryID app frame with real UI fragments floating
 * out of it, drifting on a subtle pointer/scroll parallax. Gammy peeks from
 * behind the frame.
 */
export function AppShowcase() {
  const wrapRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const wrap = wrapRef.current;
    if (!wrap || reducedMotion()) return;

    let frame = 0;
    let px = 0;
    let py = 0;
    const clamp = (n: number) =>
      Number.isFinite(n) ? Math.max(-1, Math.min(1, n)) : 0;

    const apply = () => {
      frame = 0;
      wrap.style.setProperty("--px", clamp(px).toFixed(3));
      wrap.style.setProperty("--py", clamp(py).toFixed(3));
    };
    const schedule = () => {
      if (!frame) frame = requestAnimationFrame(apply);
    };

    const onPointer = (event: PointerEvent) => {
      const rect = wrap.getBoundingClientRect();
      if (rect.width < 1 || rect.height < 1) return;
      px = ((event.clientX - rect.left) / rect.width - 0.5) * 2;
      py = ((event.clientY - rect.top) / rect.height - 0.5) * 2;
      schedule();
    };
    const onScroll = () => {
      const rect = wrap.getBoundingClientRect();
      py = (rect.top / window.innerHeight - 0.4) * 1.4;
      schedule();
    };

    window.addEventListener("pointermove", onPointer, { passive: true });
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
    return () => {
      window.removeEventListener("pointermove", onPointer);
      window.removeEventListener("scroll", onScroll);
      if (frame) cancelAnimationFrame(frame);
    };
  }, []);

  return (
    <div className="showcase" ref={wrapRef}>
      <div className="appframe">
        <div className="appframe-bar">
          <span className="appframe-dot" />
          <span className="appframe-dot" />
          <span className="appframe-dot" />
          <span className="appframe-url">app.gamoryid.com/inventory</span>
        </div>
        <div className="appframe-body">
          <AppInventoryMock />
        </div>
      </div>

      <div className="floaty floaty-toast" style={{ "--d": "1" } as CSSProperties}>
        <span className="floaty-ic is-ok">
          <CalendarCheck2 size={15} />
        </span>
        <span>
          จองสำเร็จ · <b>#23DX5</b>
          <i>ล็อกให้ลูกค้า 30 นาที</i>
        </span>
      </div>

      <div className="floaty floaty-sales" style={{ "--d": "2" } as CSSProperties}>
        <span className="floaty-sales-head">
          <span className="floaty-ic is-up">
            <TrendingUp size={15} />
          </span>
          ยอดขาย 7 วัน
        </span>
        <b className="floaty-sales-value">฿312,400</b>
        <Sparkline />
      </div>

      <div className="floaty floaty-customer" style={{ "--d": "3" } as CSSProperties}>
        <span className="floaty-avatar">บ</span>
        <span>
          คุณเบส<i>ซื้อซ้ำ 4 ครั้ง · LINE</i>
        </span>
      </div>

      <Mascot
        pose="secure"
        alt="Gammy มาสคอตของ GamoryID"
        width={210}
        className="showcase-mascot"
        priority
      />
    </div>
  );
}

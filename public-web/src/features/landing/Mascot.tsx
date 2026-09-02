type Pose = "hello" | "secure" | "search" | "sold" | "main";

/**
 * Gammy the mascot, served as optimised webp with a png fallback (≈50 KB vs the
 * 2 MB source). `priority` opts the hero image out of lazy-loading.
 */
export function Mascot({
  pose,
  alt,
  width,
  className,
  priority = false,
}: {
  pose: Pose;
  alt: string;
  width: number;
  className?: string;
  priority?: boolean;
}) {
  const base = `/mascot/opt/gammy-${pose}`;
  const height = Math.round(width); // square art
  return (
    <picture>
      <source
        type="image/webp"
        srcSet={`${base}.webp 1x, ${base}@2x.webp 2x`}
      />
      <img
        src={`${base}.png`}
        srcSet={`${base}.png 1x, ${base}@2x.png 2x`}
        alt={alt}
        width={width}
        height={height}
        className={className}
        loading={priority ? "eager" : "lazy"}
        decoding="async"
        {...(priority ? { fetchPriority: "high" as const } : {})}
      />
    </picture>
  );
}

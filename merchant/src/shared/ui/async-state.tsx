export function AsyncError({
  error,
  retry,
}: {
  error: string;
  retry: () => void;
}) {
  return (
    <div className="empty" role="alert">
      <strong>โหลดข้อมูลไม่สำเร็จ</strong>
      <p>{error}</p>
      <button className="button" onClick={retry}>
        ลองใหม่
      </button>
    </div>
  );
}

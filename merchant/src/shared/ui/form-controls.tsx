import { useState } from "react";
import type { ReactNode } from "react";
import { Eye, EyeOff, X } from "lucide-react";

export function DialogHead({
  id,
  title,
  subtitle,
  close,
}: {
  id: string;
  title: string;
  subtitle: string;
  close: () => void;
}) {
  return (
    <div className="dialog-head">
      <div>
        <h2 id={id}>{title}</h2>
        <p>{subtitle}</p>
      </div>
      <button
        type="button"
        className="icon-button"
        aria-label="ปิด"
        onClick={close}
      >
        <X size={20} />
      </button>
    </div>
  );
}

export function Field({
  label,
  children,
  full = false,
}: {
  label: string;
  children: ReactNode;
  full?: boolean;
}) {
  return (
    <div className={`field ${full ? "full" : ""}`}>
      <label>
        <span className="field-label">{label}</span>
        {children}
      </label>
    </div>
  );
}

export function PasswordInput({
  name,
  autoComplete,
  minLength,
  required = false,
  placeholder,
}: {
  name: string;
  autoComplete: string;
  minLength?: number;
  required?: boolean;
  placeholder?: string;
}) {
  const [visible, setVisible] = useState(false);

  return (
    <span className="password-control">
      <input
        name={name}
        type={visible ? "text" : "password"}
        minLength={minLength}
        required={required}
        autoComplete={autoComplete}
        placeholder={placeholder}
      />
      <button
        type="button"
        className="icon-button"
        aria-label={visible ? "ซ่อนรหัสผ่าน" : "แสดงรหัสผ่าน"}
        onClick={() => setVisible((current) => !current)}
      >
        {visible ? <EyeOff size={17} /> : <Eye size={17} />}
      </button>
    </span>
  );
}

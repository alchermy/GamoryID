import { useState } from "react";
import type { ChangeEventHandler, ReactNode } from "react";
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
  htmlFor,
}: {
  label: string;
  children: ReactNode;
  full?: boolean;
  htmlFor?: string;
}) {
  if (htmlFor) {
    return (
      <div className={`field ${full ? "full" : ""}`}>
        <label htmlFor={htmlFor}>
          <span className="field-label">{label}</span>
        </label>
        {children}
      </div>
    );
  }

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
  id,
  name,
  autoComplete,
  minLength,
  required = false,
  placeholder,
  ariaInvalid,
  ariaDescribedBy,
  onChange,
}: {
  id?: string;
  name: string;
  autoComplete: string;
  minLength?: number;
  required?: boolean;
  placeholder?: string;
  ariaInvalid?: boolean;
  ariaDescribedBy?: string;
  onChange?: ChangeEventHandler<HTMLInputElement>;
}) {
  const [visible, setVisible] = useState(false);

  return (
    <span className="password-control">
      <input
        id={id}
        name={name}
        type={visible ? "text" : "password"}
        minLength={minLength}
        required={required}
        autoComplete={autoComplete}
        placeholder={placeholder}
        aria-invalid={ariaInvalid || undefined}
        aria-describedby={ariaDescribedBy}
        onChange={onChange}
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

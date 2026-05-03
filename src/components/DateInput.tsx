import { Input } from "@/components/ui/input";
import { useEffect, useState } from "react";

// Stores ISO yyyy-mm-dd, displays dd/mm/yyyy
export function DateInput({
  value,
  onChange,
  required,
  placeholder = "dd/mm/yyyy",
  className,
}: {
  value: string; // yyyy-mm-dd
  onChange: (iso: string) => void;
  required?: boolean;
  placeholder?: string;
  className?: string;
}) {
  const isoToDisplay = (iso: string) => {
    if (!iso) return "";
    const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : iso;
  };
  const [text, setText] = useState(isoToDisplay(value));

  useEffect(() => {
    setText(isoToDisplay(value));
  }, [value]);

  const handle = (raw: string) => {
    // auto-insert slashes
    const digits = raw.replace(/\D/g, "").slice(0, 8);
    let out = digits;
    if (digits.length > 4) out = `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
    else if (digits.length > 2) out = `${digits.slice(0, 2)}/${digits.slice(2)}`;
    setText(out);

    const m = out.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (m) {
      const [, dd, mm, yyyy] = m;
      const d = new Date(`${yyyy}-${mm}-${dd}`);
      if (!isNaN(d.getTime())) onChange(`${yyyy}-${mm}-${dd}`);
    } else if (out === "") {
      onChange("");
    }
  };

  return (
    <Input
      type="text"
      inputMode="numeric"
      placeholder={placeholder}
      value={text}
      required={required}
      className={className}
      onChange={(e) => handle(e.target.value)}
    />
  );
}

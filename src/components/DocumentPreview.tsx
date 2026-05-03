import { useEffect, useState } from "react";
import { supabase } from "@/integrations/supabase/client";
import { Button } from "@/components/ui/button";
import { AlertCircle, RefreshCw, Image as ImageIcon, Loader2 } from "lucide-react";

export function DocumentPreview({ doc }: { doc: any }) {
  const [url, setUrl] = useState("");
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState("");
  const [pdfErr, setPdfErr] = useState("");
  const [retry, setRetry] = useState(0);

  useEffect(() => {
    let active = true;
    setLoading(true); setErr(""); setPdfErr(""); setUrl("");
    supabase.storage.from("kyc-documents").createSignedUrl(doc.storage_path, 600).then(({ data, error }) => {
      if (!active) return;
      if (error || !data?.signedUrl) setErr(error?.message ?? "تعذر إنشاء رابط المعاينة");
      else setUrl(data.signedUrl);
      setLoading(false);
    });
    return () => { active = false; };
  }, [doc.storage_path, retry]);

  if (loading) return <div className="flex h-40 items-center justify-center gap-2 rounded-md bg-muted/40 text-sm text-muted-foreground"><Loader2 className="size-5 animate-spin text-primary" /> جاري التحميل...</div>;
  if (err || !url) return (
    <div className="flex h-40 flex-col items-center justify-center gap-2 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-center text-xs text-destructive">
      <AlertCircle className="size-5" /><span>{err || "تعذر التحميل"}</span>
      <Button type="button" size="sm" variant="outline" onClick={() => setRetry((v) => v + 1)}><RefreshCw className="size-4" /> إعادة المحاولة</Button>
    </div>
  );
  if (doc.mime_type?.startsWith("image/")) return <img src={url} alt={doc.file_name} className="h-48 w-full rounded-md border border-border object-contain bg-muted/30" loading="lazy" />;
  if (doc.mime_type === "application/pdf") {
    if (pdfErr) return <div className="flex h-48 items-center justify-center text-xs text-destructive">{pdfErr}</div>;
    return <iframe title={doc.file_name} src={url} className="h-64 w-full rounded-md border border-border bg-background" onError={() => setPdfErr("تعذر عرض PDF")} />;
  }
  return <div className="flex h-40 items-center justify-center gap-2 text-xs text-muted-foreground"><ImageIcon className="size-5" /> لا توجد معاينة</div>;
}

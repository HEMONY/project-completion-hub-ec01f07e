import { useState, useRef, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { MessageCircle, X, Send, Minimize2 } from "lucide-react";
import { cn } from "@/lib/utils";

type Msg = { from: "bot" | "user"; text: string; at: Date };

const INITIAL: Msg = {
  from: "bot",
  text: "مرحباً بك في Muhasba! كيف يمكنني مساعدتك اليوم؟",
  at: new Date(),
};

const AUTO_REPLIES: Record<string, string> = {
  kyc: "لبدء عملية KYC، انتقل إلى قائمة 'تطبيق جديد' وأدخل بيانات الكيان.",
  "رسوم": "تُحسب رسوم المراجعة بناءً على حجم دوران الأعمال. للاستفسار، تواصل مع فريق الدعم.",
  "مساعدة": "يمكنني مساعدتك في: بدء طلب KYC، فهم مراحل العملية، أو الإجابة على أسئلتك.",
  help: "I can help you with: starting a KYC application, understanding the process, or answering your questions.",
  default: "شكراً على تواصلك. سيرد عليك أحد ممثلي الدعم قريباً. وقت الاستجابة عادةً أقل من 24 ساعة.",
};

function getAutoReply(msg: string): string {
  const lower = msg.toLowerCase();
  for (const key of Object.keys(AUTO_REPLIES)) {
    if (lower.includes(key)) return AUTO_REPLIES[key];
  }
  return AUTO_REPLIES.default;
}

export function ChatWidget() {
  const [open, setOpen] = useState(false);
  const [minimized, setMinimized] = useState(false);
  const [msgs, setMsgs] = useState<Msg[]>([INITIAL]);
  const [input, setInput] = useState("");
  const [typing, setTyping] = useState(false);
  const [unread, setUnread] = useState(0);
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [msgs, typing]);

  useEffect(() => {
    if (!open) return;
    setUnread(0);
  }, [open]);

  const send = () => {
    const text = input.trim();
    if (!text) return;
    const userMsg: Msg = { from: "user", text, at: new Date() };
    setMsgs((m) => [...m, userMsg]);
    setInput("");
    setTyping(true);
    setTimeout(() => {
      setTyping(false);
      const reply: Msg = { from: "bot", text: getAutoReply(text), at: new Date() };
      setMsgs((m) => [...m, reply]);
      if (!open) setUnread((u) => u + 1);
    }, 1200);
  };

  const fmt = (d: Date) =>
    d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });

  return (
    <div className="fixed bottom-6 end-6 z-50 flex flex-col items-end gap-2">
      {open && !minimized && (
        <div className="w-80 rounded-2xl border border-border bg-background shadow-2xl flex flex-col overflow-hidden animate-fade-in">
          {/* Header */}
          <div className="bg-primary text-primary-foreground px-4 py-3 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="size-2 rounded-full bg-green-400 animate-pulse" />
              <span className="font-medium text-sm">Muhasba Support</span>
              <span className="text-xs opacity-70">متصل</span>
            </div>
            <div className="flex items-center gap-1">
              <Button
                size="icon"
                variant="ghost"
                className="size-7 text-primary-foreground hover:bg-primary-foreground/20"
                onClick={() => setMinimized(true)}
              >
                <Minimize2 className="size-3.5" />
              </Button>
              <Button
                size="icon"
                variant="ghost"
                className="size-7 text-primary-foreground hover:bg-primary-foreground/20"
                onClick={() => setOpen(false)}
              >
                <X className="size-3.5" />
              </Button>
            </div>
          </div>

          {/* Messages */}
          <div className="flex-1 overflow-y-auto p-3 space-y-3 max-h-72 min-h-48 bg-muted/20">
            {msgs.map((m, i) => (
              <div
                key={i}
                className={cn(
                  "flex flex-col gap-0.5 max-w-[85%]",
                  m.from === "user" ? "ms-auto items-end" : "items-start"
                )}
              >
                <div
                  className={cn(
                    "px-3 py-2 rounded-2xl text-sm leading-relaxed",
                    m.from === "user"
                      ? "bg-primary text-primary-foreground rounded-br-none"
                      : "bg-card border border-border rounded-bl-none"
                  )}
                >
                  {m.text}
                </div>
                <span className="text-[10px] text-muted-foreground px-1">
                  {fmt(m.at)}
                </span>
              </div>
            ))}
            {typing && (
              <div className="flex items-start gap-1">
                <div className="bg-card border border-border px-3 py-2 rounded-2xl rounded-bl-none">
                  <div className="flex gap-1 items-center h-4">
                    {[0, 1, 2].map((i) => (
                      <div
                        key={i}
                        className="size-1.5 rounded-full bg-muted-foreground animate-bounce"
                        style={{ animationDelay: `${i * 150}ms` }}
                      />
                    ))}
                  </div>
                </div>
              </div>
            )}
            <div ref={bottomRef} />
          </div>

          {/* Input */}
          <div className="border-t border-border p-3 flex gap-2">
            <Input
              placeholder="اكتب رسالتك..."
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && send()}
              className="text-sm h-9"
            />
            <Button size="icon" className="h-9 w-9 shrink-0" onClick={send} disabled={!input.trim()}>
              <Send className="size-3.5" />
            </Button>
          </div>
        </div>
      )}

      {/* Minimized bar */}
      {open && minimized && (
        <div
          className="bg-primary text-primary-foreground rounded-full px-4 py-2 flex items-center gap-2 cursor-pointer shadow-lg text-sm"
          onClick={() => setMinimized(false)}
        >
          <div className="size-2 rounded-full bg-green-400 animate-pulse" />
          <span>Muhasba Support</span>
        </div>
      )}

      {/* Toggle button */}
      <Button
        size="icon"
        className="size-14 rounded-full shadow-2xl relative"
        onClick={() => {
          setOpen((o) => !o);
          setMinimized(false);
        }}
      >
        {open ? <X className="size-5" /> : <MessageCircle className="size-6" />}
        {!open && unread > 0 && (
          <span className="absolute -top-1 -end-1 size-5 rounded-full bg-destructive text-destructive-foreground text-[10px] font-bold grid place-items-center">
            {unread}
          </span>
        )}
      </Button>
    </div>
  );
}
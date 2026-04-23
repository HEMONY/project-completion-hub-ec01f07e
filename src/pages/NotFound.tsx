import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";

export default function NotFound() {
  return (
    <div className="min-h-screen grid place-items-center bg-background text-foreground p-6">
      <div className="text-center space-y-3">
        <h1 className="text-6xl font-bold gradient-primary bg-clip-text text-transparent">404</h1>
        <p className="text-muted-foreground">Page not found</p>
        <Button asChild variant="premium"><Link to="/">Go home</Link></Button>
      </div>
    </div>
  );
}

import { useEffect, useState } from "react";

export function usePassagePreview(original: string) {
  const [preview, setPreview] = useState<{
    original: string;
    html: string;
  } | null>(null);
  useEffect(() => {
    let active = true;
    setPreview(null);
    if (!/<img\b/i.test(original) || original.length > 200000) return;
    const prepare = async () => {
      const template = document.createElement("template");
      template.innerHTML = original;
      const images = [...template.content.querySelectorAll("img")];
      if (images.length > 50) throw Error("Too many passage images");
      for (const image of images) {
        const source = image.getAttribute("src")?.trim();
        if (!source) throw Error("Missing passage image");
        const digest = await crypto.subtle.digest(
          "SHA-256",
          new TextEncoder().encode(source),
        );
        const asset = [...new Uint8Array(digest)]
          .map((byte) => byte.toString(16).padStart(2, "0"))
          .join("");
        for (const attribute of [...image.attributes])
          image.removeAttribute(attribute.name);
        image.setAttribute("src", `t4l-media:${asset}`);
      }
      if (active) setPreview({ original, html: template.innerHTML });
    };
    void prepare().catch(() => {
      if (active) setPreview(null);
    });
    return () => {
      active = false;
    };
  }, [original]);
  return preview?.original === original ? preview.html : undefined;
}

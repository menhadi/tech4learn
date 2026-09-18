import { useEffect, useState, useRef } from "react";
import DOMPurify from "dompurify";

export const examTextTags = [
  "p",
  "div",
  "br",
  "b",
  "strong",
  "i",
  "em",
  "u",
  "s",
  "sub",
  "sup",
  "ul",
  "ol",
  "li",
  "span",
  "table",
  "thead",
  "tbody",
  "tr",
  "td",
  "th",
];
export const mathTags = [
  "math",
  "mrow",
  "mi",
  "mn",
  "mo",
  "mtext",
  "mspace",
  "msup",
  "msub",
  "msubsup",
  "mfrac",
  "msqrt",
  "mroot",
  "mover",
  "munder",
  "munderover",
  "mtable",
  "mtr",
  "mtd",
  "mlabeledtr",
  "mstyle",
  "mpadded",
  "mphantom",
  "menclose",
  "mmultiscripts",
  "mprescripts",
  "none",
  "semantics",
];
export const mathAttributes = [
  "xmlns",
  "display",
  "mathvariant",
  "displaystyle",
  "scriptlevel",
  "stretchy",
  "fence",
  "separator",
  "lspace",
  "rspace",
  "width",
  "height",
  "depth",
  "accent",
  "accentunder",
  "columnalign",
  "rowalign",
  "columnspacing",
  "rowspacing",
  "linethickness",
  "notation",
];

export async function renderExamHtml(
  value: string,
  mediaBase?: string,
): Promise<string> {
  const root = document.createElement("div");
  root.innerHTML = DOMPurify.sanitize(value, {
    ALLOWED_TAGS: [...examTextTags, ...mathTags, "img"],
    ALLOWED_ATTR: ["colspan", "rowspan", "src", "alt", ...mathAttributes],
    ALLOWED_URI_REGEXP: /^t4l-media:[a-f0-9]{64}$/,
  });
  for (const image of root.querySelectorAll("img")) {
    const source = image.getAttribute("src") ?? "";
    if (!mediaBase || !/^t4l-media:[a-f0-9]{64}$/.test(source))
      throw new Error("Image reference is unavailable.");
    image.setAttribute(
      "src",
      mediaBase.split("?", 1)[0] +
        "/" +
        source.slice("t4l-media:".length) +
        (mediaBase.includes("?")
          ? mediaBase.slice(mediaBase.indexOf("?"))
          : ""),
    );
    image.setAttribute("alt", "Question image");
    image.style.maxWidth = "100%";
  }
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  const nodes: Text[] = [];
  while (walker.nextNode()) nodes.push(walker.currentNode as Text);
  let expressions = 0;
  for (const node of nodes) {
    if (node.parentElement?.closest("math")) continue;
    const pattern =
      /\\\(([\s\S]*?)\\\)|\\\[([\s\S]*?)\\\]|\$\$([\s\S]*?)\$\$|(?<!\\)\$(?!\$)([^$\n]+?)(?<!\\)\$(?!\$)/g;
    const text = node.textContent ?? "";
    const matches = [...text.matchAll(pattern)];
    if (!matches.length) continue;
    const { texToMathML } = await import("./exam-math");
    const fragment = document.createDocumentFragment();
    let previous = 0;
    for (const match of matches) {
      if (++expressions > 150)
        throw new Error("Too many formulas in one question.");
      fragment.append(
        document.createTextNode(text.slice(previous, match.index)),
      );
      const formula = document.createElement("span");
      formula.innerHTML = DOMPurify.sanitize(
        texToMathML(
          match[1] ?? match[2] ?? match[3] ?? match[4],
          match[1] === undefined && match[4] === undefined,
        ),
        { ALLOWED_TAGS: mathTags, ALLOWED_ATTR: mathAttributes },
      );
      fragment.append(...Array.from(formula.childNodes));
      previous = match.index! + match[0].length;
    }
    fragment.append(document.createTextNode(text.slice(previous)));
    node.replaceWith(fragment);
  }
  return root.innerHTML;
}

export function ExamRichContent({
  value,
  mediaBase,
  onError,
  onReady,
}: {
  value: string;
  mediaBase?: string;
  onError?: (message: string) => void;
  onReady?: (ready: boolean) => void;
}) {
  const container = useRef<HTMLDivElement>(null);
  const [html, setHtml] = useState(""),
    [error, setError] = useState("");
  const [rendered, setRendered] = useState(false);
  useEffect(() => {
    let active = true;
    onReady?.(false);
    setHtml("");
    setError("");
    setRendered(false);
    renderExamHtml(value, mediaBase)
      .then((result) => {
        if (active) {
          setHtml(result);
          setRendered(true);
        }
      })
      .catch(() => {
        if (active) {
          const message =
            "Question content could not be displayed. Reload saved answers to retry, or contact exam staff.";
          setError(message);
          onError?.(message);
        }
      });
    return () => {
      active = false;
    };
  }, [value, mediaBase]);
  const check = () => {
    if (rendered && !error && container.current)
      onReady?.(
        [...container.current.querySelectorAll("img")].every(
          (image) => image.complete && image.naturalWidth > 0,
        ),
      );
  };
  useEffect(() => {
    check();
  }, [html, rendered, error]);
  if (error)
    return (
      <p role="alert" className="error">
        {error}
      </p>
    );
  return (
    <div
      ref={container}
      style={{ overflowX: "auto" }}
      onLoadCapture={check}
      onErrorCapture={() => {
        const message =
          "A question image failed to load. Reload saved answers to retry.";
        setError(message);
        onReady?.(false);
        onError?.(message);
      }}
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
}

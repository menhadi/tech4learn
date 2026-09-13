import { mathjax } from "@mathjax/src/js/mathjax.js";
import { TeX } from "@mathjax/src/js/input/tex.js";
import { liteAdaptor } from "@mathjax/src/js/adaptors/liteAdaptor.js";
import { RegisterHTMLHandler } from "@mathjax/src/js/handlers/html.js";
import { SerializedMmlVisitor } from "@mathjax/src/js/core/MmlTree/SerializedMmlVisitor.js";
import { STATE } from "@mathjax/src/js/core/MathItem.js";
import "@mathjax/src/js/input/tex/base/BaseConfiguration.js";
import "@mathjax/src/js/input/tex/ams/AmsConfiguration.js";
import "@mathjax/src/js/input/tex/newcommand/NewcommandConfiguration.js";
import "@mathjax/src/js/input/tex/mhchem/MhchemConfiguration.js";
import "@mathjax/src/js/input/tex/mathtools/MathtoolsConfiguration.js";

RegisterHTMLHandler(liteAdaptor());
/** No component loader, external fonts, require, URL or HTML extensions. */
export function texToMathML(source: string, display = false): string {
  if (source.length > 10000)
    throw new Error("Formula is too large to display.");
  // A fresh parser prevents definitions leaking into another question/organisation.
  const tex = new TeX({
    packages: ["base", "ams", "newcommand", "mhchem", "mathtools"],
    maxBuffer: 10000,
    maxMacros: 1000,
    formatError: (_jax: any, error: Error) => {
      throw error;
    },
  });
  const document = mathjax.document("", { InputJax: tex });
  try {
    const tree: any = document.convert(source, { display, end: STATE.CONVERT });
    return new SerializedMmlVisitor().visitTree(tree);
  } finally {
    document.clear();
  }
}

/** Map unchanged saved images to private preview identifiers, never raw image URLs. */
export function retainedFormulaPreview(
  value: string,
  original: string,
  preview: string,
): string | null {
  if ([value, original, preview].some((text) => text.length > 200000))
    return null;
  const parse = (html: string) => {
    const template = document.createElement("template");
    template.innerHTML = html;
    return template;
  };
  const saved = [...parse(original).content.querySelectorAll("img")];
  const protectedImages = [...parse(preview).content.querySelectorAll("img")];
  if (
    !saved.length ||
    saved.length > 50 ||
    saved.length !== protectedImages.length
  )
    return null;
  const sources = new Map<string, string>();
  for (let i = 0; i < saved.length; i++) {
    const source = saved[i].getAttribute("src")?.trim();
    const asset = protectedImages[i].getAttribute("src") ?? "";
    if (
      !source ||
      !/^t4l-media:[a-f0-9]{64}$/.test(asset) ||
      (sources.has(source) && sources.get(source) !== asset)
    )
      return null;
    sources.set(source, asset);
  }
  const current = parse(value);
  const images = [...current.content.querySelectorAll("img")];
  if (images.length > 50) return null;
  for (const image of images) {
    const asset = sources.get(image.getAttribute("src")?.trim() ?? "");
    if (!asset) return null;
    for (const attribute of [...image.attributes])
      image.removeAttribute(attribute.name);
    image.setAttribute("src", asset);
    image.setAttribute("alt", "Question image");
  }
  return current.innerHTML;
}

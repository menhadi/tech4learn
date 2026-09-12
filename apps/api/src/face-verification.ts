import {
  BadRequestException,
  ServiceUnavailableException,
} from "@nestjs/common";
export type Box = {
  x_min: number;
  y_min: number;
  x_max: number;
  y_max: number;
  probability: number;
};
export type Face = { box: Box; similarity: number };
export function faceConfig(org: string) {
  const enabled = (process.env.T4L_FACE_ORGANISATIONS || "")
    .split(",")
    .includes(org);
  const url = process.env.T4L_FACE_VERIFY_URL,
    key = process.env.T4L_FACE_VERIFY_KEY;
  const model = process.env.T4L_FACE_MODEL;
  const threshold = Number(process.env.T4L_FACE_THRESHOLD || "0.9"),
    margin = Number(process.env.T4L_FACE_MARGIN || "0.1");
  const configured = enabled && !!url && !!key && !!model;
  if (configured) {
    const u = new URL(url!);
    if (
      !["http:", "https:"].includes(u.protocol) ||
      u.username ||
      u.password ||
      u.search ||
      u.hash ||
      u.pathname !== "/" ||
      !Number.isFinite(threshold) ||
      threshold < 0.5 ||
      threshold > 1 ||
      !Number.isFinite(margin) ||
      margin < 0.01 ||
      margin > 0.5
    )
      throw new ServiceUnavailableException(
        "Face verification configuration is invalid.",
      );
  }
  return { configured, url, key, model, threshold, margin };
}
const fail = () =>
  new ServiceUnavailableException(
    "Face verification failed. No attendance was changed.",
  );
export function parseBox(value: unknown): Box {
  const b = value as Box;
  if (
    !b ||
    ![b.x_min, b.y_min, b.x_max, b.y_max, b.probability].every(
      Number.isFinite,
    ) ||
    b.x_min < 0 ||
    b.y_min < 0 ||
    b.x_max <= b.x_min ||
    b.y_max <= b.y_min ||
    b.x_max > 4096 ||
    b.y_max > 4096 ||
    b.probability < 0 ||
    b.probability > 1
  )
    throw fail();
  return {
    x_min: b.x_min,
    y_min: b.y_min,
    x_max: b.x_max,
    y_max: b.y_max,
    probability: b.probability,
  };
}
export async function verifyFace(org: string, source: Buffer, target: Buffer) {
  const c = faceConfig(org);
  if (!c.configured)
    throw new BadRequestException(
      "The private face verification service is not configured for this organisation.",
    );
  let response: Response;
  try {
    response = await fetch(
      `${c.url!.replace(/\/$/, "")}/api/v1/verification/verify?limit=0&det_prob_threshold=0.8&status=false`,
      {
        method: "POST",
        redirect: "error",
        signal: AbortSignal.timeout(12000),
        headers: { "Content-Type": "application/json", "x-api-key": c.key! },
        body: JSON.stringify({
          source_image: source.toString("base64"),
          target_image: target.toString("base64"),
        }),
      },
    );
  } catch {
    throw fail();
  }
  if (!response.ok) {
    await response.body?.cancel();
    throw fail();
  }
  const reader = response.body?.getReader();
  if (!reader) throw fail();
  let size = 0;
  const chunks: Uint8Array[] = [];
  try {
    while (true) {
      const r = await reader.read();
      if (r.done) break;
      size += r.value.length;
      if (size > 200000) {
        await reader.cancel();
        throw fail();
      }
      chunks.push(r.value);
    }
  } catch {
    throw fail();
  }
  let data: any;
  try {
    data = JSON.parse(Buffer.concat(chunks).toString());
  } catch {
    throw fail();
  }
  if (!Array.isArray(data.result) || data.result.length !== 1)
    throw new BadRequestException(
      "Use a reference photo containing exactly one clearly visible face.",
    );
  const sourceBox = parseBox(data.result[0].source_image_face?.box),
    matches = data.result[0].face_matches;
  if (!Array.isArray(matches) || matches.length > 100) throw fail();
  const faces: Face[] = matches.map((m: any) => {
    if (!Number.isFinite(m.similarity) || m.similarity < 0 || m.similarity > 1)
      throw fail();
    return { box: parseBox(m.box), similarity: m.similarity };
  });
  return { sourceBox, faces, model: c.model! };
}
export function sameFace(a: Box, b: Box) {
  const intersection =
    Math.max(0, Math.min(a.x_max, b.x_max) - Math.max(a.x_min, b.x_min)) *
    Math.max(0, Math.min(a.y_max, b.y_max) - Math.max(a.y_min, b.y_min));
  const area = (x: Box) => (x.x_max - x.x_min) * (x.y_max - x.y_min);
  return intersection / (area(a) + area(b) - intersection) > 0.65;
}

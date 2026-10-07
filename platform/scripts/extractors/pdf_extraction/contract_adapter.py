from collections import defaultdict
from pathlib import Path

from extraction_contract import ImageManifest


class LegacyImageAdapter:
    def __init__(self, context, legacy_images_dir):
        self.context = context
        self.legacy_images_dir = Path(legacy_images_dir)
        self.manifest = ImageManifest(context)
        self.source_orders = defaultdict(int)
        self.reference_map = {}

    def adopt(
        self,
        question_no,
        role,
        filenames,
        *,
        source_pages=None,
        metadata_by_filename=None,
    ):
        source_pages = source_pages or []
        metadata_by_filename = metadata_by_filename or {}
        adopted = []
        for image_index, old_name in enumerate(filenames or [], start=1):
            if old_name in self.reference_map:
                adopted.append(self.reference_map[old_name])
                continue

            old_path = self.legacy_images_dir / old_name
            if not old_path.exists():
                raise FileNotFoundError(f"Referenced legacy image does not exist: {old_path}")
            image_bytes = old_path.read_bytes()
            extension = old_path.suffix.lstrip(".") or "png"
            new_name = self.context.image_filename(question_no, role, image_index, extension)
            new_path = self.context.images_dir / new_name
            new_path.write_bytes(image_bytes)

            self.source_orders[str(question_no)] += 1
            details = metadata_by_filename.get(old_name, {})
            source_page = details.get("page", source_pages[0] if source_pages else "")
            manifest_row = self.manifest.add(
                question_no=question_no,
                role=role,
                image_index=image_index,
                filename=new_name,
                source_order=self.source_orders[str(question_no)],
                source_page=source_page,
                bbox=details.get("bbox"),
                width=details.get("width", ""),
                height=details.get("height", ""),
                image_bytes=image_bytes,
            )
            relative_path = manifest_row["relative_path"]
            self.reference_map[old_name] = relative_path
            adopted.append(relative_path)
            if old_path.resolve() != new_path.resolve():
                old_path.unlink()
        return adopted

    def rewrite_text_references(self, value):
        rewritten = value or ""
        for old_name, relative_path in self.reference_map.items():
            rewritten = rewritten.replace(old_name, relative_path)
        return rewritten

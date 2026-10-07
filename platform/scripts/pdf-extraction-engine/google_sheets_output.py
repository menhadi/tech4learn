import argparse
import json
import csv
from pathlib import Path

from cdn_output import DEFAULT_CDN_PREFIX, validate_questions_csv_cdn
from extraction_contract import DELIVERY_IMAGE_FIELDS


DEFAULT_GOOGLE_SHEET_ID = "1ZMpRmB1Kd1X7PXZKEshWUzGcqZAUZ4BMG4pVpclgkQo"
DEFAULT_CREDENTIALS_FILE = "service-account.json"
DEFAULT_SHEET_TAB = "data"
SHEETS_SCOPE = "https://www.googleapis.com/auth/spreadsheets"
MAX_CELL_CHARACTERS = 50000


def _build_service(credentials_file):
    try:
        from google.oauth2 import service_account
        from googleapiclient.discovery import build
    except ImportError as exc:
        raise RuntimeError(
            "Google Sheets support is not installed. Run: "
            "python -m pip install -r requirements.txt"
        ) from exc

    credentials = service_account.Credentials.from_service_account_file(
        str(credentials_file), scopes=[SHEETS_SCOPE]
    )
    return build("sheets", "v4", credentials=credentials, cache_discovery=False)


def _sheet_range(tab, cell=""):
    escaped = str(tab).replace("'", "''")
    return f"'{escaped}'!{cell}" if cell else f"'{escaped}'"


def load_csv_values(csv_path):
    csv_path = Path(csv_path)
    with csv_path.open(encoding="utf-8-sig", newline="") as source:
        raw_rows = list(csv.reader(source))
    if not raw_rows:
        raise ValueError(f"CSV has no header row: {csv_path}")
    included_indexes = [
        index for index, field in enumerate(raw_rows[0])
        if field not in DELIVERY_IMAGE_FIELDS
    ]
    values = []
    for row_number, row in enumerate(raw_rows, start=1):
        normalized = []
        for column_index in included_indexes:
            value = str(row[column_index] if column_index < len(row) else "")
            if len(value) > MAX_CELL_CHARACTERS:
                raise ValueError(
                    f"Google Sheets cell limit exceeded at row {row_number}, "
                    f"column {column_index + 1}: {len(value)} characters"
                )
            normalized.append(value)
        values.append(normalized)
    return values

def _ensure_tab(service, spreadsheet_id, tab):
    metadata = service.spreadsheets().get(
        spreadsheetId=spreadsheet_id,
        fields="sheets.properties.title",
    ).execute()
    titles = {
        sheet.get("properties", {}).get("title")
        for sheet in metadata.get("sheets", [])
    }
    if tab not in titles:
        service.spreadsheets().batchUpdate(
            spreadsheetId=spreadsheet_id,
            body={"requests": [{"addSheet": {"properties": {"title": tab}}}]},
        ).execute()


def publish_questions_csv(
    csv_path,
    spreadsheet_id=DEFAULT_GOOGLE_SHEET_ID,
    credentials_file=DEFAULT_CREDENTIALS_FILE,
    tab=DEFAULT_SHEET_TAB,
    chunk_rows=500,
    service=None,
    cdn_prefix=DEFAULT_CDN_PREFIX,
):
    csv_path = Path(csv_path).resolve()
    credentials_file = Path(credentials_file).resolve()
    if not csv_path.exists():
        raise FileNotFoundError(f"Questions CSV not found: {csv_path}")
    if service is None and not credentials_file.exists():
        raise FileNotFoundError(
            f"Google service-account credentials not found: {credentials_file}"
        )
    if chunk_rows < 1:
        raise ValueError("chunk_rows must be at least 1")

    validate_questions_csv_cdn(csv_path, cdn_prefix)
    values = load_csv_values(csv_path)
    service = service or _build_service(credentials_file)
    _ensure_tab(service, spreadsheet_id, tab)
    values_api = service.spreadsheets().values()
    values_api.clear(
        spreadsheetId=spreadsheet_id,
        range=_sheet_range(tab),
        body={},
    ).execute()

    updated_rows = 0
    for offset in range(0, len(values), chunk_rows):
        chunk = values[offset:offset + chunk_rows]
        values_api.update(
            spreadsheetId=spreadsheet_id,
            range=_sheet_range(tab, f"A{offset + 1}"),
            valueInputOption="RAW",
            body={"majorDimension": "ROWS", "values": chunk},
        ).execute()
        updated_rows += len(chunk)

    return {
        "spreadsheet_id": spreadsheet_id,
        "tab": tab,
        "header_rows": 1,
        "question_rows": max(0, updated_rows - 1),
        "written_rows": updated_rows,
        "source_csv": str(csv_path),
    }

def main():
    parser = argparse.ArgumentParser(
        description="Publish an extracted questions.csv file to Google Sheets."
    )
    parser.add_argument("--csv", required=True)
    parser.add_argument("--google-sheet-id", default=DEFAULT_GOOGLE_SHEET_ID)
    parser.add_argument("--google-credentials", default=DEFAULT_CREDENTIALS_FILE)
    parser.add_argument("--google-sheet-tab", default=DEFAULT_SHEET_TAB)
    parser.add_argument("--google-chunk-rows", type=int, default=500)
    parser.add_argument("--cdn-prefix", default=DEFAULT_CDN_PREFIX)
    args = parser.parse_args()
    result = publish_questions_csv(
        args.csv,
        spreadsheet_id=args.google_sheet_id,
        credentials_file=args.google_credentials,
        tab=args.google_sheet_tab,
        chunk_rows=args.google_chunk_rows,
        cdn_prefix=args.cdn_prefix,
    )
    print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()

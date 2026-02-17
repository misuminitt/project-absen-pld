#!/usr/bin/env python3
"""Clean attendance export into spreadsheet-friendly format.

Usage:
  python scripts/clean_absen.py --input "C:\\path\\Absen Panha.xlsx" --output "absen_clean.xlsx"
  python scripts/clean_absen.py --input "C:\\path\\Absen Panha.xlsx" --sheet-url "https://docs.google.com/spreadsheets/..." --service-account "C:\\path\\service-account.json"
  python scripts/clean_absen.py --input "C:\\path\\Absen Panha.xlsx" --mode rekap-nama --output "rekap_absen.xlsx"
  python scripts/clean_absen.py --input "C:\\path\\Absen Panha.xlsx" --sheet-url "https://docs.google.com/spreadsheets/..." --service-account "C:\\path\\service-account.json" --worksheet "Data Absen" --data-start-row 3 --output-dir "src/secrets/output"
"""

from __future__ import annotations

import argparse
import datetime as dt
import json
import os
import re
from collections import Counter
from pathlib import Path
from typing import Iterable

import pandas as pd

DEFAULT_OUTPUT_DIR = Path("src/secrets/output")


FINAL_COLUMNS = [
    "No",
    "Nama Karyawan",
    "Alamat",
    "Tlp",
    "Tanggal",
    "Nama Shift",
    "Waktu Masuk",
    "Telat",
    "Waktu Pulang",
    "Durasi Bekerja",
    "Jenis",
    "Foto Masuk",
    "Foto Pulang",
]

SUMMARY_COLUMNS = [
    "No",
    "Nama Karyawan",
    "Alamat",
    "Tlp",
    "Tanggal Absen",
    "Nama Shift",
    "Waktu Masuk",
    "Telat",
    "Waktu Pulang",
    "Durasi Bekerja",
    "Jenis Masuk",
    "Jenis Pulang",
    "Foto Masuk",
    "Foto Pulang",
    "Sudah Berapa Absen",
    "Hari Efektif",
    "Total Hadir",
    "1-30 Menit",
    "31-60 Menit",
    "1-3 Jam",
    "<4 Jam",
    "Total Izin/Cuti",
    "Total Alpha",
    "Alasan Telat",
    "Alasan Izin/Cuti",
    "Alasan Alpha",
]


COLUMN_ALIASES = {
    "No": ["no", "nomor"],
    "Nama Karyawan": ["nama karyawan", "nama"],
    "Alamat": ["alamat"],
    "Tlp": ["tlp", "telepon", "no hp", "nomor telepon"],
    "Tanggal": ["tanggal", "tgl", "date"],
    "Nama Shift": ["nama shift", "shift"],
    "Waktu Masuk": ["waktu masuk", "jam masuk", "clock in"],
    "Telat": ["telat", "terlambat", "late"],
    "Waktu Pulang": ["waktu pulang", "jam pulang", "clock out"],
    "Durasi Bekerja": ["durasi bekerja", "durasi", "lama bekerja", "work duration"],
    "Jenis": ["jenis", "type"],
    "Foto Masuk": ["foto masuk", "gambar masuk", "selfie masuk"],
    "Foto Pulang": ["foto pulang", "gambar pulang", "selfie pulang"],
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Clean absensi export and deduplicate per Nama Karyawan + Tanggal."
    )
    parser.add_argument("--input", required=True, help="Path file input .xlsx")
    parser.add_argument("--output", help="Path file output (.xlsx/.csv)")
    parser.add_argument(
        "--output-dir",
        default=str(DEFAULT_OUTPUT_DIR),
        help=(
            "Folder output default jika --output tidak diisi "
            '(default: "src/secrets/output").'
        ),
    )
    parser.add_argument("--sheet", help="Nama sheet (default: sheet pertama)")
    parser.add_argument(
        "--sheet-url",
        help="URL Google Sheet tujuan (opsional). Jika diisi, data akan di-upload.",
    )
    parser.add_argument(
        "--worksheet",
        default="Data Absen",
        help="Nama tab/worksheet Google Sheet (default: Data Absen).",
    )
    parser.add_argument(
        "--service-account",
        help=(
            "Path file JSON service account Google API untuk upload ke Google Sheet. "
            "Bisa juga lewat env GOOGLE_APPLICATION_CREDENTIALS. "
            'Jika tidak diisi, script akan coba auto-detect file JSON di folder "src/secrets".'
        ),
    )
    parser.add_argument(
        "--mode",
        choices=["detail", "rekap-nama"],
        default="rekap-nama",
        help=(
            "Mode output. rekap-nama = 1 baris per nama per bulan "
            "(tanpa duplikat nama di bulan yang sama). "
            "detail = 1 baris per nama+tanggal."
        ),
    )
    parser.add_argument(
        "--data-start-row",
        type=int,
        default=3,
        help=(
            "Baris awal isi data saat upload ke Google Sheet. "
            "Default 3 agar header template (row 1-2) tetap aman."
        ),
    )
    parser.add_argument(
        "--write-header",
        action="store_true",
        help="Jika diaktifkan, tulis juga header kolom ke sheet pada baris data-start-row.",
    )
    return parser.parse_args()


def normalize_text(value: object) -> str:
    if pd.isna(value):
        return ""
    text = str(value).strip()
    if text.lower() == "nan":
        return ""
    return text


def header_key(value: object) -> str:
    text = normalize_text(value).lower()
    text = re.sub(r"\s+", " ", text)
    return text


def find_header_row(excel_path: str, sheet_name: str | int) -> int:
    raw = pd.read_excel(excel_path, sheet_name=sheet_name, header=None)
    required = {"no", "nama karyawan", "tanggal"}
    max_scan = min(len(raw), 60)
    for idx in range(max_scan):
        row_values = {header_key(cell) for cell in raw.iloc[idx].tolist() if normalize_text(cell)}
        if required.issubset(row_values):
            return idx
    raise ValueError(
        "Header tabel tidak ditemukan. Pastikan ada kolom No, Nama Karyawan, dan Tanggal."
    )


def build_column_map(df_columns: Iterable[object]) -> dict[str, str]:
    normalized_to_actual: dict[str, str] = {}
    for col in df_columns:
        key = header_key(col)
        if key:
            normalized_to_actual[key] = str(col)

    result: dict[str, str] = {}
    for canonical, aliases in COLUMN_ALIASES.items():
        for alias in aliases:
            if alias in normalized_to_actual:
                result[canonical] = normalized_to_actual[alias]
                break
    return result


def first_non_empty(values: Iterable[object]) -> str:
    for value in values:
        text = normalize_text(value)
        if text:
            return text
    return ""


def split_input_meta(value: object) -> str:
    text = normalize_text(value)
    if not text:
        return ""
    # Format dari export: "07:59:56 AM, Di input oleh XXX"
    return text.split(",")[0].strip()


def parse_time_to_seconds(value: str) -> int | None:
    if not value:
        return None
    patterns = ["%H:%M:%S", "%H:%M", "%I:%M:%S %p", "%I:%M %p"]
    for pattern in patterns:
        try:
            parsed = dt.datetime.strptime(value, pattern)
            return parsed.hour * 3600 + parsed.minute * 60 + parsed.second
        except ValueError:
            continue
    parsed_generic = pd.to_datetime(value, errors="coerce")
    if pd.isna(parsed_generic):
        return None
    return parsed_generic.hour * 3600 + parsed_generic.minute * 60 + parsed_generic.second


def normalize_time(value: object) -> str:
    raw = split_input_meta(value)
    if not raw:
        return ""
    seconds = parse_time_to_seconds(raw)
    if seconds is None:
        return raw
    hours = seconds // 3600
    minutes = (seconds % 3600) // 60
    secs = seconds % 60
    return f"{hours:02d}:{minutes:02d}:{secs:02d}"


def normalize_date(value: object) -> str:
    parsed = pd.to_datetime(value, errors="coerce")
    if pd.isna(parsed):
        return normalize_text(value)
    return parsed.strftime("%Y-%m-%d")


def pick_earliest_time(values: Iterable[object]) -> str:
    candidates: list[tuple[int, str]] = []
    fallback: list[str] = []
    for value in values:
        text = normalize_time(value)
        if not text:
            continue
        fallback.append(text)
        seconds = parse_time_to_seconds(text)
        if seconds is not None:
            candidates.append((seconds, text))
    if candidates:
        return min(candidates, key=lambda item: item[0])[1]
    return fallback[0] if fallback else ""


def pick_latest_time(values: Iterable[object]) -> str:
    candidates: list[tuple[int, str]] = []
    fallback: list[str] = []
    for value in values:
        text = normalize_time(value)
        if not text:
            continue
        fallback.append(text)
        seconds = parse_time_to_seconds(text)
        if seconds is not None:
            candidates.append((seconds, text))
    if candidates:
        return max(candidates, key=lambda item: item[0])[1]
    return fallback[-1] if fallback else ""


def choose_duration(values: Iterable[object], start_time: str, end_time: str) -> str:
    direct_value = first_non_empty(values)
    if direct_value:
        return direct_value

    start_sec = parse_time_to_seconds(start_time)
    end_sec = parse_time_to_seconds(end_time)
    if start_sec is None or end_sec is None:
        return ""

    duration_sec = end_sec - start_sec
    if duration_sec < 0:
        duration_sec += 24 * 3600

    hours = duration_sec // 3600
    minutes = (duration_sec % 3600) // 60
    seconds = duration_sec % 60
    return f"{hours:02d} jam {minutes} menit {seconds} detik"


def derive_jenis(foto_masuk: str, foto_pulang: str) -> str:
    labels: list[str] = []
    if normalize_text(foto_masuk):
        labels.append("Absen Masuk")
    if normalize_text(foto_pulang):
        labels.append("Absen Pulang")
    return ", ".join(labels)


def extract_month(value: object) -> str:
    parsed = pd.to_datetime(value, errors="coerce")
    if pd.isna(parsed):
        text = normalize_text(value)
        return text[:7] if len(text) >= 7 else text
    return parsed.strftime("%Y-%m")


def parse_duration_to_minutes(value: object) -> float:
    text = normalize_text(value).lower()
    if not text:
        return 0.0

    hours_match = re.search(r"(\d+)\s*jam", text)
    minutes_match = re.search(r"(\d+)\s*menit", text)
    seconds_match = re.search(r"(\d+)\s*detik", text)
    if hours_match or minutes_match or seconds_match:
        hours = int(hours_match.group(1)) if hours_match else 0
        minutes = int(minutes_match.group(1)) if minutes_match else 0
        seconds = int(seconds_match.group(1)) if seconds_match else 0
        return float(hours * 60 + minutes + (seconds / 60.0))

    time_seconds = parse_time_to_seconds(text)
    if time_seconds is not None:
        return float(time_seconds) / 60.0

    td = pd.to_timedelta(text, errors="coerce")
    if pd.isna(td):
        return 0.0
    return float(td.total_seconds() / 60.0)


def most_common_non_empty(values: Iterable[object]) -> str:
    cleaned_values = [normalize_text(value) for value in values if normalize_text(value)]
    if not cleaned_values:
        return ""
    counts = Counter(cleaned_values)
    return counts.most_common(1)[0][0]


def build_date_range(values: Iterable[object]) -> str:
    raw_values = [normalize_text(value) for value in values if normalize_text(value)]
    if not raw_values:
        return ""

    parsed_dates = pd.to_datetime(pd.Series(raw_values), errors="coerce").dropna()
    if parsed_dates.empty:
        unique_text = sorted(set(raw_values))
        if len(unique_text) == 1:
            return unique_text[0]
        return f"{unique_text[0]} s/d {unique_text[-1]}"

    min_date = parsed_dates.min().strftime("%Y-%m-%d")
    max_date = parsed_dates.max().strftime("%Y-%m-%d")
    if min_date == max_date:
        return min_date
    return f"{min_date} s/d {max_date}"


def build_cleaned_dataframe(input_path: str, sheet_name: str | None = None) -> pd.DataFrame:
    selected_sheet: str | int = sheet_name if sheet_name is not None else 0
    header_row = find_header_row(input_path, selected_sheet)
    df_raw = pd.read_excel(input_path, sheet_name=selected_sheet, header=header_row)

    column_map = build_column_map(df_raw.columns)
    cleaned = pd.DataFrame()
    for col in FINAL_COLUMNS:
        source = column_map.get(col)
        cleaned[col] = df_raw[source] if source else ""

    cleaned["Nama Karyawan"] = cleaned["Nama Karyawan"].apply(normalize_text)
    cleaned["Tanggal"] = cleaned["Tanggal"].apply(normalize_date)
    cleaned = cleaned[
        (cleaned["Nama Karyawan"] != "")
        & (cleaned["Tanggal"] != "")
        & (~cleaned["Nama Karyawan"].str.lower().eq("nama karyawan"))
    ].copy()

    grouped_rows: list[dict[str, str]] = []
    grouped = cleaned.groupby(["Nama Karyawan", "Tanggal"], sort=True, dropna=False)
    for (_, _), group in grouped:
        foto_masuk = first_non_empty(group["Foto Masuk"])
        foto_pulang = first_non_empty(group["Foto Pulang"])
        waktu_masuk = pick_earliest_time(group["Waktu Masuk"])
        waktu_pulang = pick_latest_time(group["Waktu Pulang"])

        row = {
            "No": "",
            "Nama Karyawan": first_non_empty(group["Nama Karyawan"]),
            "Alamat": first_non_empty(group["Alamat"]),
            "Tlp": first_non_empty(group["Tlp"]),
            "Tanggal": first_non_empty(group["Tanggal"]),
            "Nama Shift": first_non_empty(group["Nama Shift"]),
            "Waktu Masuk": waktu_masuk,
            "Telat": first_non_empty(group["Telat"]),
            "Waktu Pulang": waktu_pulang,
            "Durasi Bekerja": choose_duration(group["Durasi Bekerja"], waktu_masuk, waktu_pulang),
            "Foto Masuk": foto_masuk,
            "Foto Pulang": foto_pulang,
            "Jenis": derive_jenis(foto_masuk, foto_pulang),
        }
        grouped_rows.append(row)

    final_df = pd.DataFrame(grouped_rows, columns=FINAL_COLUMNS)
    final_df["No"] = range(1, len(final_df) + 1)
    final_df = final_df[FINAL_COLUMNS]

    return final_df


def build_monthly_name_summary(detail_df: pd.DataFrame) -> pd.DataFrame:
    if detail_df.empty:
        return pd.DataFrame(columns=SUMMARY_COLUMNS)

    work = detail_df.copy()
    work["Bulan"] = work["Tanggal"].apply(extract_month)
    work["Sort Tanggal"] = pd.to_datetime(work["Tanggal"], errors="coerce")
    work["Has Foto Masuk"] = work["Foto Masuk"].apply(lambda value: 1 if normalize_text(value) else 0)
    work["Has Foto Pulang"] = work["Foto Pulang"].apply(lambda value: 1 if normalize_text(value) else 0)
    work["Telat Menit"] = work["Telat"].apply(parse_duration_to_minutes)
    work["Is Izin/Cuti"] = work["Jenis"].apply(
        lambda value: 1 if re.search(r"(izin|cuti)", normalize_text(value).lower()) else 0
    )
    work["Is Alpha"] = work["Jenis"].apply(
        lambda value: 1 if re.search(r"(alpha|alpa)", normalize_text(value).lower()) else 0
    )

    hari_efektif_per_bulan = (
        work.groupby("Bulan", dropna=False)["Tanggal"]
        .nunique()
        .to_dict()
    )

    grouped_rows: list[dict[str, object]] = []
    grouped = work.groupby(["Nama Karyawan", "Bulan"], sort=True, dropna=False)
    for (_, bulan), group in grouped:
        group_sorted = group.sort_values("Sort Tanggal", ascending=True, na_position="last")
        latest_row = group_sorted.iloc[-1]

        total_hadir = int(len(group))
        total_izin_cuti = int(group["Is Izin/Cuti"].sum())
        explicit_alpha = int(group["Is Alpha"].sum())
        hari_efektif = int(hari_efektif_per_bulan.get(bulan, total_hadir))
        auto_alpha = max(hari_efektif - total_hadir - total_izin_cuti, 0)
        total_alpha = max(explicit_alpha, auto_alpha)

        telat_menit = group["Telat Menit"]
        total_telat_1_30 = int(((telat_menit >= 1) & (telat_menit <= 30)).sum())
        total_telat_31_60 = int(((telat_menit > 30) & (telat_menit <= 60)).sum())
        total_telat_1_3_jam = int(((telat_menit > 60) & (telat_menit <= 180)).sum())
        # Kolom diminta "<4 Jam"; nilai di atas 3 jam tetap dihitung di bucket ini.
        total_telat_lt_4_jam = int((telat_menit > 180).sum())

        jenis_masuk = "Absen Masuk" if int(group["Has Foto Masuk"].sum()) > 0 else ""
        jenis_pulang = "Absen Pulang" if int(group["Has Foto Pulang"].sum()) > 0 else ""

        grouped_rows.append(
            {
                "No": "",
                "Nama Karyawan": first_non_empty(group["Nama Karyawan"]),
                "Alamat": first_non_empty(group["Alamat"]),
                "Tlp": first_non_empty(group["Tlp"]),
                "Tanggal Absen": build_date_range(group["Tanggal"]),
                "Nama Shift": most_common_non_empty(group["Nama Shift"]),
                "Waktu Masuk": normalize_text(latest_row["Waktu Masuk"]),
                "Telat": normalize_text(latest_row["Telat"]),
                "Waktu Pulang": normalize_text(latest_row["Waktu Pulang"]),
                "Durasi Bekerja": normalize_text(latest_row["Durasi Bekerja"]),
                "Jenis Masuk": jenis_masuk,
                "Jenis Pulang": jenis_pulang,
                "Foto Masuk": normalize_text(latest_row["Foto Masuk"]),
                "Foto Pulang": normalize_text(latest_row["Foto Pulang"]),
                "Sudah Berapa Absen": total_hadir,
                "Hari Efektif": hari_efektif,
                "Total Hadir": total_hadir,
                "1-30 Menit": total_telat_1_30,
                "31-60 Menit": total_telat_31_60,
                "1-3 Jam": total_telat_1_3_jam,
                "<4 Jam": total_telat_lt_4_jam,
                "Total Izin/Cuti": total_izin_cuti,
                "Total Alpha": total_alpha,
                "Alasan Telat": "",
                "Alasan Izin/Cuti": "",
                "Alasan Alpha": "",
            }
        )

    summary_df = pd.DataFrame(grouped_rows, columns=SUMMARY_COLUMNS)
    summary_df["No"] = range(1, len(summary_df) + 1)
    summary_df = summary_df[SUMMARY_COLUMNS]
    return summary_df


def write_output(df: pd.DataFrame, output_path: str) -> Path:
    output = Path(output_path)
    output.parent.mkdir(parents=True, exist_ok=True)
    if output.suffix.lower() == ".csv":
        df.to_csv(output, index=False, encoding="utf-8-sig")
    else:
        df.to_excel(output, index=False)
    return output


def resolve_service_account_path(cli_path: str | None) -> str:
    def add_candidate(target: str | Path | None, bucket: list[Path], seen: set[str]) -> None:
        if target is None:
            return
        path_text = str(target).strip()
        if not path_text:
            return
        path_obj = Path(path_text).expanduser()
        unique_key = str(path_obj).lower()
        if unique_key in seen:
            return
        seen.add(unique_key)
        bucket.append(path_obj)

    def is_valid_service_account_json(path: Path) -> bool:
        try:
            raw = path.read_text(encoding="utf-8")
            payload = json.loads(raw)
        except Exception:
            return False
        if not isinstance(payload, dict):
            return False
        client_email = str(payload.get("client_email", "")).strip()
        private_key = str(payload.get("private_key", "")).strip()
        return bool(client_email and private_key)

    candidates: list[Path] = []
    seen_candidates: set[str] = set()
    add_candidate(cli_path, candidates, seen_candidates)
    add_candidate(os.environ.get("GOOGLE_APPLICATION_CREDENTIALS", ""), candidates, seen_candidates)
    add_candidate("src/secrets/panha-database-spreedsheet-e7201f6cbb11.json", candidates, seen_candidates)
    add_candidate("src/secrets/service-account.json", candidates, seen_candidates)

    secrets_dir = Path("src/secrets")
    if secrets_dir.is_dir():
        for json_file in sorted(secrets_dir.glob("*.json")):
            add_candidate(json_file, candidates, seen_candidates)

    existing_json_candidates = [
        path for path in candidates if path.exists() and path.suffix.lower() == ".json"
    ]
    for credential_path in existing_json_candidates:
        if is_valid_service_account_json(credential_path):
            return str(credential_path)

    searched_paths = "\n".join(f"- {path}" for path in candidates) if candidates else "- (tidak ada)"
    raise FileNotFoundError(
        "File service account JSON valid tidak ditemukan.\n"
        "Pastikan file JSON service account ada dan berisi client_email/private_key.\n"
        f"Path yang dicek:\n{searched_paths}"
    )


def column_index_to_letter(index: int) -> str:
    if index <= 0:
        raise ValueError("Index kolom harus lebih dari 0.")
    result = []
    value = index
    while value > 0:
        value, remainder = divmod(value - 1, 26)
        result.append(chr(65 + remainder))
    return "".join(reversed(result))


def upload_to_google_sheet(
    df: pd.DataFrame,
    sheet_url: str,
    worksheet_name: str,
    service_account_path: str,
    data_start_row: int = 3,
    write_header: bool = False,
) -> None:
    try:
        import gspread
    except ImportError as exc:
        raise RuntimeError(
            "Library gspread belum terpasang. Install dulu: python -m pip install gspread google-auth"
        ) from exc

    try:
        gc = gspread.service_account(filename=service_account_path)
        spreadsheet = gc.open_by_url(sheet_url)
    except Exception as exc:
        raise RuntimeError(
            "Gagal autentikasi atau akses spreadsheet. "
            "Pastikan JSON service account valid, URL Google Sheet benar, "
            "dan spreadsheet sudah di-share ke email service account (role Editor)."
        ) from exc

    try:
        worksheet = spreadsheet.worksheet(worksheet_name)
    except gspread.WorksheetNotFound:
        worksheet = spreadsheet.add_worksheet(
            title=worksheet_name,
            rows=max(len(df) + 10, 1000),
            cols=len(df.columns) + 3,
        )

    prepared = df.fillna("").astype(str)
    values = prepared.values.tolist()
    if write_header:
        values = [prepared.columns.tolist()] + values

    if not values:
        return

    start_row = max(1, int(data_start_row))
    last_column = column_index_to_letter(len(prepared.columns))
    clear_end_row = max(worksheet.row_count, start_row + len(values) + 100)
    worksheet.batch_clear([f"A{start_row}:{last_column}{clear_end_row}"])
    worksheet.update(
        values=values,
        range_name=f"A{start_row}",
        value_input_option="USER_ENTERED",
    )


def main() -> None:
    args = parse_args()
    input_path = str(Path(args.input))
    detail_df = build_cleaned_dataframe(input_path, sheet_name=args.sheet)
    if args.mode == "rekap-nama":
        final_df = build_monthly_name_summary(detail_df)
    else:
        final_df = detail_df

    output_path = args.output
    if output_path is None:
        suffix = "_rekap_nama.xlsx" if args.mode == "rekap-nama" else "_cleaned.xlsx"
        output_dir = Path(args.output_dir)
        output_path = str(output_dir / f"{Path(input_path).stem}{suffix}")
    output_written = False
    try:
        output = write_output(final_df, output_path)
        output_written = True
        print(f"Berhasil simpan file bersih: {output}")
    except PermissionError as exc:
        if args.output is None:
            output_obj = Path(output_path)
            fallback_name = (
                f"{output_obj.stem}_{dt.datetime.now().strftime('%Y%m%d_%H%M%S')}{output_obj.suffix or '.xlsx'}"
            )
            fallback_path = output_obj.with_name(fallback_name)
            output = write_output(final_df, str(fallback_path))
            output_written = True
            print(
                f"Peringatan: file output sedang dipakai ({output_path}). "
                f"Disimpan ke file baru: {output}"
            )
        elif args.sheet_url:
            print(
                f"Peringatan: file output sedang dipakai aplikasi lain, skip simpan lokal dulu ({output_path}). "
                "Lanjut upload ke Google Sheet."
            )
        else:
            raise PermissionError(
                f"Gagal simpan file output karena file sedang dipakai: {output_path}\n"
                "Tutup file tersebut dulu atau gunakan --output ke nama/path lain."
            ) from exc

    if args.sheet_url:
        service_account_path = resolve_service_account_path(args.service_account)
        upload_to_google_sheet(
            final_df,
            sheet_url=args.sheet_url,
            worksheet_name=args.worksheet,
            service_account_path=service_account_path,
            data_start_row=args.data_start_row,
            write_header=args.write_header,
        )
        print(
            f"Berhasil upload ke Google Sheet tab '{args.worksheet}': {args.sheet_url}"
        )
    elif not output_written:
        raise RuntimeError("File output tidak tersimpan.")


if __name__ == "__main__":
    main()

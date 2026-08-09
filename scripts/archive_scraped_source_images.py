#!/usr/bin/env python3
"""Privately preserve source images referenced by Viruxe's scraped article drafts."""

from __future__ import annotations

import argparse
import base64
import hashlib
import ipaddress
import json
import os
import re
import shutil
import socket
import subprocess
import tempfile
import time
import unicodedata
from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any
from urllib.parse import unquote, urljoin, urlparse, urlunparse

import requests
from bs4 import BeautifulSoup, Tag
from PIL import Image


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_ARCHIVE_ROOT = ROOT / "storage/app/private/source-image-archive"
USER_AGENT = "Hondabase private source preservation archive (+https://www.hondabase.com)"
IMAGE_EXTENSIONS = "avif|bmp|gif|ico|jpe?g|png|svg|tiff?|webp"
URL_IMAGE_RE = re.compile(
    rf"https?://[^\s\"'<>\\]+?\.(?:{IMAGE_EXTENSIONS})(?:\?[^\s\"'<>\\]*)?",
    re.IGNORECASE,
)
WIX_MEDIA_RE = re.compile(
    rf"https?://static\.wixstatic\.com/media/([-A-Za-z0-9_.%~]+\.(?:{IMAGE_EXTENSIONS}))",
    re.IGNORECASE,
)
IMAGE_PATH_RE = re.compile(rf"\.(?:{IMAGE_EXTENSIONS})$", re.IGNORECASE)
GENERIC_CONTEXT_RE = re.compile(
    r"^(?:image|photo|picture|thumbnail|logo|фото в бортжурнале|изображение)(?:\b|\s|$)",
    re.IGNORECASE,
)
OPAQUE_STEM_RE = re.compile(
    r"^(?:\d+[a-z]?|(?:dsc|img|image|photo|pic)[-_]?\d+|[0-9a-f]{7,}[a-z]?)(?:-(?:960|1920))?$",
    re.IGNORECASE,
)
TRANSIENT_STATUSES = {403, 408, 425, 429, 500, 502, 503, 504}


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--apply", action="store_true", help="write archive objects, views, and a manifest")
    parser.add_argument("--refresh-pages", action="store_true", help="ignore archived source-page snapshots")
    parser.add_argument(
        "--retry-unavailable",
        action="store_true",
        help="retry image URLs recorded as unavailable in the latest manifest",
    )
    parser.add_argument("--archive-root", type=Path, default=DEFAULT_ARCHIVE_ROOT)
    parser.add_argument("--max-image-bytes", type=int, default=50 * 1024 * 1024)
    parser.add_argument("--seed-ntf-directory", type=Path)
    return parser.parse_args()


def load_draft_sources() -> dict[str, Any]:
    result = subprocess.run(
        ["php", str(ROOT / "scripts/export-scraped-draft-sources.php")],
        cwd=ROOT,
        check=True,
        capture_output=True,
        text=True,
    )
    return json.loads(result.stdout)


def load_json(path: Path, default: Any) -> Any:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (FileNotFoundError, json.JSONDecodeError, OSError):
        return default


def write_json_atomic(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile("w", encoding="utf-8", dir=path.parent, delete=False) as handle:
        json.dump(value, handle, ensure_ascii=False, indent=2)
        handle.write("\n")
        temporary = Path(handle.name)
    os.chmod(temporary, 0o640)
    temporary.replace(path)


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def safe_remote_url(url: str) -> None:
    parsed = urlparse(url)
    if parsed.scheme not in {"http", "https"} or not parsed.hostname:
        raise ValueError("only public HTTP(S) URLs can be archived")
    host = parsed.hostname.rstrip(".").lower()
    if host == "localhost" or host.endswith((".localhost", ".local")):
        raise ValueError("local network URL rejected")
    try:
        addresses = {item[4][0] for item in socket.getaddrinfo(host, parsed.port or 443)}
    except socket.gaierror:
        addresses = set()
    for address in addresses:
        ip = ipaddress.ip_address(address)
        if not ip.is_global:
            raise ValueError("private or reserved network target rejected")


class HostPacer:
    def __init__(self) -> None:
        self.last_request: dict[str, float] = {}

    def wait(self, url: str, *, page: bool = False) -> None:
        host = (urlparse(url).hostname or "").lower()
        if host.endswith("icelord.net"):
            interval = 3.0 if page else 0.35
        elif host == "static.wixstatic.com":
            interval = 0.1
        else:
            interval = 0.25
        remaining = interval - (time.monotonic() - self.last_request.get(host, 0.0))
        if remaining > 0:
            time.sleep(remaining)
        self.last_request[host] = time.monotonic()


def page_request_url(source_url: str) -> str:
    parsed = urlparse(source_url)
    if parsed.hostname == "home.icelord.net" and parsed.path.startswith("/wordpress/"):
        return urlunparse(parsed._replace(netloc="icelord.net"))
    return source_url


def source_page_key(url: str) -> str:
    parsed = urlparse(url)
    query_post = re.search(r"(?:^|&)p=(\d+)(?:&|$)", parsed.query)
    if query_post:
        return f"post-{query_post.group(1)}"
    components = [piece for piece in parsed.path.split("/") if piece]
    if components and "." in components[-1]:
        components[-1] = components[-1].rsplit(".", 1)[0]
    return slugify("-".join(components[-3:]) or parsed.hostname or "source", 80)


def group_source_records(records: list[dict[str, Any]]) -> list[dict[str, Any]]:
    grouped: dict[tuple[str, str], dict[str, Any]] = {}
    for record in records:
        key = (record["site"], record["source_page_url"])
        grouped.setdefault(
            key,
            {"site": record["site"], "source_page_url": record["source_page_url"], "records": []},
        )["records"].append(record)
    return list(grouped.values())


def store_bytes_object(archive_root: Path, body: bytes, extension: str) -> dict[str, Any]:
    digest = hashlib.sha256(body).hexdigest()
    relative = Path("objects/sha256") / digest[:2] / f"{digest}.{extension}"
    destination = archive_root / relative
    destination.parent.mkdir(parents=True, exist_ok=True)
    if not destination.exists():
        with tempfile.NamedTemporaryFile("wb", dir=destination.parent, delete=False) as handle:
            handle.write(body)
            temporary = Path(handle.name)
        os.chmod(temporary, 0o640)
        temporary.replace(destination)
    return {"sha256": digest, "bytes": destination.stat().st_size, "object_path": relative.as_posix()}


def store_file_object(archive_root: Path, source: Path, extension: str) -> dict[str, Any]:
    digest = sha256_file(source)
    relative = Path("objects/sha256") / digest[:2] / f"{digest}.{extension}"
    destination = archive_root / relative
    destination.parent.mkdir(parents=True, exist_ok=True)
    if not destination.exists():
        try:
            source.replace(destination)
        except OSError:
            shutil.copyfile(source, destination)
        os.chmod(destination, 0o640)
    source.unlink(missing_ok=True)
    return {"sha256": digest, "bytes": destination.stat().st_size, "object_path": relative.as_posix()}


def fetch_source_page(session: requests.Session, url: str, pacer: HostPacer) -> dict[str, Any]:
    current = page_request_url(url)
    for _ in range(10):
        safe_remote_url(current)
        pacer.wait(current, page=True)
        response = session.get(
            current,
            allow_redirects=False,
            headers={"Accept": "text/html,application/xhtml+xml;q=0.9,*/*;q=0.5", "Connection": "close"},
            timeout=(20, 30),
        )
        if 300 <= response.status_code < 400 and response.headers.get("Location"):
            current = urljoin(current, response.headers["Location"])
            continue
        response.raise_for_status()
        return {
            "body": response.content,
            "content_type": response.headers.get("Content-Type"),
            "final_url": current,
            "http_status": response.status_code,
            "fetched_at": utc_now(),
            "fetch_warning": None,
        }
    raise RuntimeError("too many source-page redirects")


def cached_source_page(archive_root: Path, entry: Any) -> dict[str, Any] | None:
    if not isinstance(entry, dict) or not isinstance(entry.get("object_path"), str):
        return None
    path = archive_root / entry["object_path"]
    if not path.is_file() or sha256_file(path) != entry.get("sha256"):
        return None
    return {
        "body": path.read_bytes(),
        "content_type": entry.get("content_type"),
        "final_url": entry.get("final_url"),
        "http_status": entry.get("http_status", 200),
        "fetched_at": entry.get("fetched_at", utc_now()),
        "fetch_warning": entry.get("fetch_warning"),
    }


def seed_ntf_page(directory: Path | None, url: str) -> dict[str, Any] | None:
    if directory is None:
        return None
    slug = Path(urlparse(url).path).name
    path = directory / f"{slug}.html"
    if not path.is_file() or path.stat().st_size < 1000:
        return None
    return {
        "body": path.read_bytes(),
        "content_type": "text/html",
        "final_url": url,
        "http_status": 200,
        "fetched_at": datetime.fromtimestamp(path.stat().st_mtime, timezone.utc).isoformat(),
        "fetch_warning": "Reused the HTML snapshot from the article-inventory scrape.",
    }


def decode_url_escapes(value: str) -> str:
    return (
        value.replace("\\/", "/")
        .replace("\\u002F", "/")
        .replace("\\u002f", "/")
        .replace("\\u003A", ":")
        .replace("\\u003a", ":")
        .replace("\\u0026", "&")
    )


def parse_srcset(value: str) -> list[str]:
    return [item.strip().split()[0] for item in value.split(",") if item.strip()]


def looks_like_image_url(url: str) -> bool:
    return bool(IMAGE_PATH_RE.search(urlparse(url).path))


def canonical_image_url(url: str) -> str:
    parsed = urlparse(url)
    if parsed.hostname == "static.wixstatic.com":
        match = re.match(rf"^/media/([^/]+\.(?:{IMAGE_EXTENSIONS}))", parsed.path, re.IGNORECASE)
        if match:
            return f"https://static.wixstatic.com/media/{unquote(match.group(1))}"
    return urlunparse(parsed._replace(fragment=""))


def malformed_candidate(url: str, method: str, page_url: str) -> bool:
    parsed = urlparse(url)
    if method == "page-data:image-url" and not looks_like_image_url(url):
        return True
    page_host = (urlparse(page_url).hostname or "").lower()
    candidate_host = (parsed.hostname or "").lower()
    return bool(
        page_host.endswith("nthefastlane.com")
        and candidate_host.endswith("nthefastlane.com")
        and re.match(r"^/(?:\d+|quality_auto/|(?:al|enc|h|lg|q|usm)_)", parsed.path, re.IGNORECASE)
    )


def clean_context(value: Any) -> str | None:
    if not isinstance(value, str):
        return None
    cleaned = re.sub(r"\s+", " ", value).strip()
    return cleaned[:500] if cleaned else None


def tag_context(tag: Tag) -> dict[str, str | None]:
    figure = tag.find_parent("figure")
    caption_tag = figure.find("figcaption") if figure else None
    title = tag.get("title")
    parent = tag.parent
    while not title and isinstance(parent, Tag) and parent is not figure:
        title = parent.get("title")
        parent = parent.parent
    return {
        "alt": clean_context(tag.get("alt")),
        "caption": clean_context(caption_tag.get_text(" ", strip=True) if caption_tag else None),
        "title": clean_context(title),
    }


def source_filename(url: str) -> str | None:
    if url.startswith("data:image/"):
        return None
    name = unquote(Path(urlparse(url).path).name).strip()
    return name or None


def extract_image_occurrences(html: bytes, page_url: str, site: str) -> list[dict[str, Any]]:
    text = decode_url_escapes(html.decode("utf-8", errors="replace"))
    soup = BeautifulSoup(text, "lxml")
    roots: list[Tag | BeautifulSoup]
    content_roots = soup.select(".entry-content")
    roots = content_roots if site == "Icelord" and content_roots else [soup]
    scope_name = "article-content" if roots is content_roots else "page"
    occurrences: dict[tuple[str, str, str, str, str], dict[str, Any]] = {}
    order = 0

    def add(raw_url: str | None, method: str, context: dict[str, Any] | None = None) -> None:
        nonlocal order
        if not raw_url:
            return
        raw_url = decode_url_escapes(str(raw_url)).replace('\\"', '"').strip(" \t\r\n\"'\\")
        if not raw_url or raw_url.startswith("blob:"):
            return
        embedded_body: bytes | None = None
        if raw_url.startswith("data:image/"):
            match = re.match(r"^data:image/([a-z0-9.+-]+)(?:;charset=[^;,]+)?(;base64)?,(.*)$", raw_url, re.I | re.S)
            if not match:
                return
            try:
                embedded_body = base64.b64decode(match.group(3), validate=True) if match.group(2) else unquote(match.group(3)).encode()
            except (ValueError, base64.binascii.Error):
                return
            canonical = f"data:image/{match.group(1).lower()};sha256={hashlib.sha256(embedded_body).hexdigest()}"
            discovered = canonical
        else:
            discovered = urljoin(page_url, raw_url)
            parsed = urlparse(discovered)
            if parsed.scheme not in {"http", "https"} or not parsed.hostname:
                return
            canonical = canonical_image_url(discovered)
            if malformed_candidate(canonical, method, page_url):
                return
        order += 1
        context = context or {}
        item = {
            "canonical_url": canonical,
            "discovered_url": discovered,
            "method": method,
            "alt": clean_context(context.get("alt")),
            "caption": clean_context(context.get("caption")),
            "title": clean_context(context.get("title")),
            "source_filename": source_filename(discovered),
            "order": order,
            "embedded_body": embedded_body,
        }
        key = (canonical, method, item["alt"] or "", item["caption"] or "", item["title"] or "")
        occurrences[key] = item

    for root in roots:
        for tag in root.select("img, source, input[type=image]"):
            context = tag_context(tag)
            for attribute in ("src", "data-src", "data-lazy-src", "data-original"):
                add(tag.get(attribute), f"{scope_name}:{tag.name}/{attribute}", context)
            for attribute in ("srcset", "data-srcset"):
                for url in parse_srcset(tag.get(attribute, "")):
                    add(url, f"{scope_name}:{tag.name}/{attribute}", context)
        for tag in root.select("a[href]"):
            if looks_like_image_url(tag.get("href", "")):
                add(tag.get("href"), f"{scope_name}:a/href", tag_context(tag))
        for tag in root.select("[style]"):
            for match in re.findall(r"url\([\"']?([^\"')]+)", tag.get("style", ""), re.I):
                add(match, f"{scope_name}:style/url", tag_context(tag))

    for tag in soup.select("meta[content]"):
        name = f"{tag.get('property', '')} {tag.get('name', '')}".lower()
        if "og:image" in name or "twitter:image" in name:
            add(tag.get("content"), "page-metadata:image")

    for match in URL_IMAGE_RE.finditer(text):
        add(match.group(0).rstrip("),.;]}"), "page-data:image-url")
    for media_id in WIX_MEDIA_RE.findall(text):
        add(f"https://static.wixstatic.com/media/{media_id}", "wix-media:original")

    return list(occurrences.values())


def merge_candidate(
    candidates: dict[str, dict[str, Any]],
    occurrence: dict[str, Any],
    source_record: dict[str, Any],
) -> None:
    canonical = occurrence["canonical_url"]
    candidate = candidates.setdefault(
        canonical,
        {"canonical_url": canonical, "embedded_body": occurrence.pop("embedded_body"), "occurrences": {}, "role": None},
    )
    occurrence.pop("embedded_body", None)
    enriched = {
        **occurrence,
        "site": source_record["site"],
        "draft_id": source_record["draft_id"],
        "draft_title": source_record["draft_title"],
        "article_path": source_record["article_path"],
        "source_page_url": source_record["source_page_url"],
        "source_title": source_record.get("source_title"),
    }
    key = "|".join(
        str(enriched.get(part) or "")
        for part in ("draft_id", "source_page_url", "discovered_url", "method", "alt", "caption", "title")
    )
    candidate["occurrences"][key] = enriched


def classify_candidates(candidates: dict[str, dict[str, Any]]) -> None:
    for candidate in candidates.values():
        occurrences = list(candidate["occurrences"].values())
        page_count = len({item["source_page_url"] for item in occurrences})
        methods = [item["method"] for item in occurrences]
        if page_count > 5:
            role = "site-wide"
        elif any(method.startswith("article-content:") for method in methods):
            role = "article-content"
        else:
            role = "page-specific-or-metadata"
        candidate["role"] = role
        candidate["occurrences"] = occurrences


def prior_image_results(archive_root: Path) -> dict[str, dict[str, Any]]:
    manifest = load_json(archive_root / "manifests/latest.json", {})
    results: dict[str, dict[str, Any]] = {}
    for image in manifest.get("images", []):
        if image.get("canonical_url") and (
            (image.get("object_path") and image.get("sha256")) or image.get("error")
        ):
            results[canonical_image_url(image["canonical_url"])] = image
    return results


def cached_image_result(
    archive_root: Path, candidate: dict[str, Any], previous: dict[str, Any] | None
) -> dict[str, Any] | None:
    if not previous:
        return None
    if previous.get("error") and not previous.get("object_path"):
        result = result_shell(candidate)
        for key in ("final_url", "http_status", "fetched_at", "error"):
            result[key] = previous.get(key)
        return result
    path = archive_root / previous.get("object_path", "")
    if not path.is_file() or sha256_file(path) != previous.get("sha256"):
        return None
    result = result_shell(candidate)
    for key in (
        "final_url",
        "http_status",
        "content_type",
        "mime_type",
        "bytes",
        "sha256",
        "object_path",
        "etag",
        "last_modified",
        "fetched_at",
        "width",
        "height",
    ):
        result[key] = previous.get(key)
    if not result["mime_type"] or result["width"] is None or result["height"] is None:
        mime, _, width, height = inspect_image(path, result["content_type"])
        result["mime_type"] = mime
        result["width"] = width
        result["height"] = height
    return result


def result_shell(candidate: dict[str, Any]) -> dict[str, Any]:
    return {
        "canonical_url": candidate["canonical_url"],
        "role": candidate["role"],
        "licensing_status": "unresolved-all-rights-reserved",
        "publication_cleared": False,
        "original_modified": False,
        "occurrences": candidate["occurrences"],
        "final_url": None,
        "http_status": None,
        "content_type": None,
        "mime_type": None,
        "bytes": None,
        "sha256": None,
        "object_path": None,
        "etag": None,
        "last_modified": None,
        "fetched_at": None,
        "width": None,
        "height": None,
        "alias_paths": [],
        "error": None,
    }


def inspect_image(path: Path, content_type: str | None) -> tuple[str, str, int | None, int | None]:
    start = path.read_bytes()[:4096].lstrip()
    if start.startswith(b"<svg") or b"<svg" in start[:1000]:
        return "image/svg+xml", "svg", None, None
    try:
        with Image.open(path) as image:
            image.verify()
        with Image.open(path) as image:
            mime = Image.MIME.get(image.format or "", content_type or "image/unknown")
            extension = {
                "JPEG": "jpg",
                "PNG": "png",
                "GIF": "gif",
                "WEBP": "webp",
                "AVIF": "avif",
                "BMP": "bmp",
                "TIFF": "tif",
                "ICO": "ico",
            }.get(image.format or "", (image.format or "img").lower())
            return mime, extension, image.width, image.height
    except Exception as error:
        raise RuntimeError(f"response is not a complete recognized image: {error}") from error


def download_image(
    session: requests.Session,
    candidate: dict[str, Any],
    archive_root: Path,
    max_bytes: int,
    pacer: HostPacer,
) -> dict[str, Any]:
    if candidate["canonical_url"].startswith("data:image/"):
        body = candidate.get("embedded_body")
        if not isinstance(body, bytes) or not body:
            raise RuntimeError("embedded image bytes are unavailable")
        with tempfile.NamedTemporaryFile("wb", dir=archive_root / "tmp", delete=False) as handle:
            handle.write(body)
            temporary = Path(handle.name)
        mime, extension, width, height = inspect_image(temporary, None)
        stored = store_file_object(archive_root, temporary, extension)
        return {
            **stored,
            "final_url": candidate["canonical_url"],
            "http_status": 200,
            "content_type": mime,
            "mime_type": mime,
            "etag": None,
            "last_modified": None,
            "fetched_at": utc_now(),
            "width": width,
            "height": height,
        }

    (archive_root / "tmp").mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile("wb", dir=archive_root / "tmp", delete=False) as handle:
        temporary = Path(handle.name)
    current = candidate["canonical_url"]
    referer = candidate["occurrences"][0].get("source_page_url")
    content_type = etag = last_modified = None
    delays = (0,) + (0.25,) * 31
    alternate_host_attempted = False
    stagnant_attempts = 0
    transient_attempts = 0

    try:
        for attempt, delay in enumerate(delays):
            if delay:
                time.sleep(delay)
            safe_remote_url(current)
            pacer.wait(current)
            offset = temporary.stat().st_size
            headers = {
                "Accept": "image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8",
                "Connection": "close",
            }
            if referer:
                headers["Referer"] = referer
            if offset:
                headers["Range"] = f"bytes={offset}-"

            try:
                response = session.get(current, headers=headers, allow_redirects=False, stream=True, timeout=(15, 2))
            except requests.RequestException as error:
                stagnant_attempts += 1
                if stagnant_attempts >= 4 or attempt == len(delays) - 1:
                    raise RuntimeError(str(error)) from error
                continue

            if 300 <= response.status_code < 400 and response.headers.get("Location"):
                current = urljoin(current, response.headers["Location"])
                response.close()
                continue
            if response.status_code == 200 and offset:
                temporary.write_bytes(b"")
                response.close()
                continue
            if response.status_code not in {200, 206}:
                status = response.status_code
                response.close()
                if status == 403 and not alternate_host_attempted:
                    parsed_current = urlparse(current)
                    if parsed_current.hostname in {"home.icelord.net", "icelord.net"}:
                        alternate = "icelord.net" if parsed_current.hostname == "home.icelord.net" else "home.icelord.net"
                        current = urlunparse(parsed_current._replace(netloc=alternate))
                        alternate_host_attempted = True
                        continue
                if status in TRANSIENT_STATUSES and attempt < len(delays) - 1:
                    transient_attempts += 1
                    if transient_attempts >= 4:
                        raise RuntimeError(f"HTTP {status}")
                    continue
                raise RuntimeError(f"HTTP {status}")

            expected = None
            content_range = response.headers.get("Content-Range", "")
            range_match = re.search(r"/(\d+)$", content_range)
            if range_match:
                expected = int(range_match.group(1))
            elif response.status_code == 200 and response.headers.get("Content-Length", "").isdigit():
                expected = int(response.headers["Content-Length"])
            if expected is not None and expected > max_bytes:
                response.close()
                raise RuntimeError(f"image exceeds configured {max_bytes} byte limit")

            content_type = response.headers.get("Content-Type", content_type)
            etag = response.headers.get("ETag", etag)
            last_modified = response.headers.get("Last-Modified", last_modified)
            completed = False
            try:
                with temporary.open("ab") as output:
                    for chunk in response.iter_content(64 * 1024):
                        if not chunk:
                            continue
                        if output.tell() + len(chunk) > max_bytes:
                            raise RuntimeError(f"image exceeds configured {max_bytes} byte limit")
                        output.write(chunk)
                completed = expected is None or temporary.stat().st_size >= expected
            except requests.RequestException:
                completed = False
            finally:
                response.close()

            if completed:
                mime, extension, width, height = inspect_image(temporary, content_type)
                stored = store_file_object(archive_root, temporary, extension)
                return {
                    **stored,
                    "final_url": current,
                    "http_status": 200,
                    "content_type": content_type,
                    "mime_type": mime,
                    "etag": etag,
                    "last_modified": last_modified,
                    "fetched_at": utc_now(),
                    "width": width,
                    "height": height,
                }

            new_size = temporary.stat().st_size
            stagnant_attempts = stagnant_attempts + 1 if new_size <= offset else 0
            if stagnant_attempts >= 4:
                raise RuntimeError("download made no progress across resumable retries")

        raise RuntimeError("download remained incomplete after resumable retries")
    except Exception:
        temporary.unlink(missing_ok=True)
        raise


def image_family_key(url: str) -> str:
    parsed = urlparse(url)
    path = re.sub(r"-(?:960|1920)(?=\.[^.]+$)", "", parsed.path, flags=re.IGNORECASE)
    return urlunparse(parsed._replace(path=path, query="", fragment=""))


def slugify(value: str, maximum: int = 96) -> str:
    value = unicodedata.normalize("NFKC", value).casefold().replace("_", "-")
    value = re.sub(r"[^\w-]+", "-", value, flags=re.UNICODE)
    value = re.sub(r"-+", "-", value).strip("-.")
    return (value or "image")[:maximum].rstrip("-.")


def meaningful_context(value: str | None) -> bool:
    return bool(value and len(value) > 2 and not GENERIC_CONTEXT_RE.match(value))


def opaque_stem(value: str) -> bool:
    if OPAQUE_STEM_RE.match(value):
        return True
    if value.casefold().endswith("-mv2") and len(value) >= 16:
        return True
    tokens = [token for token in re.split(r"[-_]", value) if token]
    if len(tokens) >= 2 and sum(token.isalpha() for token in tokens) >= 2:
        return False
    return bool(
        len(value) >= 10
        and re.search(r"[a-z]", value)
        and re.search(r"[A-Z]", value)
        and re.search(r"\d", value)
    )


def alias_stem(occurrence: dict[str, Any], site_wide: bool) -> tuple[str, str]:
    for field in ("caption", "title", "alt"):
        value = occurrence.get(field)
        if meaningful_context(value):
            return slugify(value), field

    original = occurrence.get("source_filename")
    original_stem = Path(original).stem if original else ""
    if original_stem and not opaque_stem(original_stem):
        return slugify(original_stem), "source-filename"

    if site_wide:
        fallback = f"{occurrence['site']}-site-asset"
    else:
        article_slug = occurrence["article_path"].split("/")[-1]
        fallback = f"{article_slug}-image"
    if original_stem:
        fallback += f"-{original_stem[:24]}"
    return slugify(fallback), "article-context-fallback"


def occurrence_rank(occurrence: dict[str, Any]) -> tuple[int, int]:
    score = 0
    if meaningful_context(occurrence.get("caption")):
        score += 40
    if meaningful_context(occurrence.get("title")):
        score += 30
    if meaningful_context(occurrence.get("alt")):
        score += 20
    original = occurrence.get("source_filename")
    if original and not opaque_stem(Path(original).stem):
        score += 10
    if occurrence.get("method", "").endswith("a/href"):
        score += 5
    return score, -int(occurrence.get("order", 0))


def replace_human_readable_views(archive_root: Path, staging_root: Path) -> None:
    view_names = ("by-article", "by-site")
    backups: dict[str, Path] = {}
    installed: list[str] = []

    try:
        for view_name in view_names:
            current = archive_root / view_name
            staged = staging_root / view_name
            backup = archive_root / "tmp" / f"{view_name}-previous-{os.getpid()}"
            staged.mkdir(parents=True, exist_ok=True)
            if backup.exists():
                shutil.rmtree(backup)
            if current.exists():
                current.rename(backup)
                backups[view_name] = backup
            staged.rename(current)
            installed.append(view_name)
    except Exception:
        for view_name in reversed(installed):
            current = archive_root / view_name
            if current.exists():
                shutil.rmtree(current)
        for view_name, backup in backups.items():
            current = archive_root / view_name
            if not current.exists() and backup.exists():
                backup.rename(current)
        raise
    else:
        for backup in backups.values():
            shutil.rmtree(backup)
        shutil.rmtree(staging_root, ignore_errors=True)


def make_aliases(archive_root: Path, image_results: list[dict[str, Any]]) -> int:
    staging_root = archive_root / "tmp" / f"views-{os.getpid()}-{time.time_ns()}"
    for view_name in ("by-article", "by-site"):
        (staging_root / view_name).mkdir(parents=True, exist_ok=True)
    counters: dict[Path, int] = defaultdict(int)
    indexes: dict[Path, dict[str, Any]] = {}
    alias_count = 0

    for image in image_results:
        if not image.get("object_path"):
            continue
        object_path = archive_root / image["object_path"]
        extension = object_path.suffix.lstrip(".") or "img"
        occurrences = image["occurrences"]
        alias_groups: list[tuple[Path, list[dict[str, Any]], dict[str, Any]]] = []

        if image["role"] == "site-wide":
            by_site: dict[str, list[dict[str, Any]]] = defaultdict(list)
            for occurrence in occurrences:
                by_site[slugify(occurrence["site"])].append(occurrence)
            for site_slug, grouped in by_site.items():
                directory = staging_root / "by-site" / site_slug / "site-wide" / "images"
                alias_groups.append((directory, grouped, {"site": grouped[0]["site"], "role": "site-wide"}))
        else:
            by_source: dict[tuple[int, str], list[dict[str, Any]]] = defaultdict(list)
            for occurrence in occurrences:
                by_source[(occurrence["draft_id"], occurrence["source_page_url"])].append(occurrence)
            for (_, source_url), grouped in by_source.items():
                first = grouped[0]
                directory = (
                    staging_root
                    / "by-article"
                    / first["article_path"]
                    / slugify(first["site"])
                    / source_page_key(source_url)
                    / "images"
                )
                alias_groups.append(
                    (
                        directory,
                        grouped,
                        {
                            "site": first["site"],
                            "role": image["role"],
                            "draft_id": first["draft_id"],
                            "draft_title": first["draft_title"],
                            "article_path": first["article_path"],
                            "source_page_url": source_url,
                        },
                    )
                )

        for directory, grouped, index_context in alias_groups:
            directory.mkdir(parents=True, exist_ok=True)
            counters[directory] += 1
            sequence = counters[directory]
            best = max(grouped, key=occurrence_rank)
            stem, naming_source = alias_stem(best, image["role"] == "site-wide")
            alias = directory / f"{sequence:03d}-{stem}.{extension}"
            if alias.exists():
                try:
                    if os.path.samefile(alias, object_path):
                        pass
                    elif sha256_file(alias) == image["sha256"]:
                        pass
                    else:
                        alias = directory / f"{sequence:03d}-{stem}-{image['sha256'][:8]}.{extension}"
                except OSError:
                    alias = directory / f"{sequence:03d}-{stem}-{image['sha256'][:8]}.{extension}"
            if not alias.exists():
                try:
                    os.link(object_path, alias)
                except OSError:
                    shutil.copyfile(object_path, alias)
                os.chmod(alias, 0o640)

            relative_alias = alias.relative_to(staging_root).as_posix()
            image["alias_paths"].append(relative_alias)
            alias_count += 1
            index = indexes.setdefault(directory.parent, {**index_context, "generated_at": utc_now(), "images": []})
            index["images"].append(
                {
                    "filename": alias.name,
                    "naming_source": naming_source,
                    "source_original_filename": best.get("source_filename"),
                    "caption": best.get("caption"),
                    "title": best.get("title"),
                    "alt": best.get("alt"),
                    "source_image_url": best.get("discovered_url"),
                    "canonical_url": image["canonical_url"],
                    "sha256": image["sha256"],
                    "bytes": image["bytes"],
                    "width": image.get("width"),
                    "height": image.get("height"),
                    "object_path": image["object_path"],
                }
            )

    for directory, index in indexes.items():
        write_json_atomic(directory / "index.json", index)
    replace_human_readable_views(archive_root, staging_root)
    return alias_count


def main() -> int:
    args = parse_args()
    archive_root: Path = args.archive_root.resolve()
    if args.apply:
        for directory in ("objects/sha256", "cache", "manifests", "tmp"):
            (archive_root / directory).mkdir(parents=True, exist_ok=True)

    source_export = load_draft_sources()
    source_records = source_export["sources"]
    grouped_pages = group_source_records(source_records)
    page_cache_path = archive_root / "cache/source-pages.json"
    page_cache = load_json(page_cache_path, {})
    session = requests.Session()
    session.headers.update({"User-Agent": USER_AGENT, "Accept-Language": "en-GB,en;q=0.8"})
    pacer = HostPacer()
    source_pages: list[dict[str, Any]] = []
    candidates: dict[str, dict[str, Any]] = {}

    print(f"Mode: {'archive' if args.apply else 'dry run'}", flush=True)
    print(f"Source occurrences: {len(source_records)}", flush=True)
    print(f"Unique source pages: {len(grouped_pages)}", flush=True)

    for page_number, group in enumerate(grouped_pages, 1):
        source_url = group["source_page_url"]
        label = f"[page {page_number}/{len(grouped_pages)}] {source_url}"
        try:
            page = None if args.refresh_pages else cached_source_page(archive_root, page_cache.get(source_url))
            if page is None and group["site"] == "Nthefastlane":
                page = seed_ntf_page(args.seed_ntf_directory, source_url)
            if page is None:
                page = fetch_source_page(session, source_url, pacer)
            page_object = store_bytes_object(archive_root, page["body"], "html") if args.apply else None
            if args.apply and page_object:
                page_cache[source_url] = {
                    **page_object,
                    "content_type": page["content_type"],
                    "final_url": page["final_url"],
                    "http_status": page["http_status"],
                    "fetched_at": page["fetched_at"],
                    "fetch_warning": page.get("fetch_warning"),
                }
                write_json_atomic(page_cache_path, page_cache)
            extracted = extract_image_occurrences(page["body"], page["final_url"], group["site"])
            for occurrence in extracted:
                for record in group["records"]:
                    merge_candidate(candidates, dict(occurrence), record)
            source_pages.append(
                {
                    "site": group["site"],
                    "source_page_url": source_url,
                    "final_url": page["final_url"],
                    "http_status": page["http_status"],
                    "content_type": page["content_type"],
                    "bytes": len(page["body"]),
                    "sha256": hashlib.sha256(page["body"]).hexdigest(),
                    "object_path": page_object["object_path"] if page_object else None,
                    "fetched_at": page["fetched_at"],
                    "fetch_warning": page.get("fetch_warning"),
                    "drafts": [
                        {"draft_id": record["draft_id"], "article_path": record["article_path"]}
                        for record in group["records"]
                    ],
                    "error": None,
                }
            )
            print(f"{label} -> {len(extracted)} contextual image references", flush=True)
        except Exception as error:
            source_pages.append(
                {
                    "site": group["site"],
                    "source_page_url": source_url,
                    "final_url": None,
                    "http_status": None,
                    "content_type": None,
                    "bytes": None,
                    "sha256": None,
                    "object_path": None,
                    "fetched_at": utc_now(),
                    "fetch_warning": None,
                    "drafts": [
                        {"draft_id": record["draft_id"], "article_path": record["article_path"]}
                        for record in group["records"]
                    ],
                    "error": str(error)[:500],
                }
            )
            print(f"{label} -> ERROR: {error}", flush=True)

    classify_candidates(candidates)
    previous = prior_image_results(archive_root)
    image_cache_path = archive_root / "cache/image-results.json"
    image_cache = load_json(image_cache_path, {})
    previous.update(image_cache)
    image_results: list[dict[str, Any]] = []
    candidate_list = list(candidates.values())
    print(f"Unique normalized image references: {len(candidate_list)}", flush=True)

    for image_number, candidate in enumerate(candidate_list, 1):
        prefix = f"[image {image_number}/{len(candidate_list)}]"
        result = result_shell(candidate)
        if not args.apply:
            image_results.append(result)
            continue
        prior_result = previous.get(candidate["canonical_url"])
        if args.retry_unavailable and prior_result and prior_result.get("error"):
            prior_result = None
        cached = cached_image_result(archive_root, candidate, prior_result)
        if cached:
            image_results.append(cached)
            cached_value = cached["object_path"] or f"ERROR: {cached['error']}"
            print(f"{prefix} {candidate['canonical_url']} -> {cached_value} [cached]", flush=True)
            continue
        try:
            download = download_image(session, candidate, archive_root, args.max_image_bytes, pacer)
            result.update(download)
            image_results.append(result)
            image_cache[candidate["canonical_url"]] = {
                key: result.get(key)
                for key in (
                    "canonical_url",
                    "final_url",
                    "http_status",
                    "content_type",
                    "mime_type",
                    "bytes",
                    "sha256",
                    "object_path",
                    "etag",
                    "last_modified",
                    "fetched_at",
                    "width",
                    "height",
                )
            }
            write_json_atomic(image_cache_path, image_cache)
            print(f"{prefix} {candidate['canonical_url']} -> {result['object_path']}", flush=True)
        except Exception as error:
            result["error"] = str(error)[:500]
            image_results.append(result)
            print(f"{prefix} {candidate['canonical_url']} -> ERROR: {error}", flush=True)

    successful_families = {
        image_family_key(image["canonical_url"]) for image in image_results if image.get("object_path")
    }
    for image in image_results:
        if image.get("error") and image_family_key(image["canonical_url"]) in successful_families:
            image["preservation_status"] = "alternate-resolution-variant-preserved"
        elif image.get("object_path"):
            image["preservation_status"] = "preserved"
        else:
            image["preservation_status"] = "unavailable"

    alias_count = make_aliases(archive_root, image_results) if args.apply else 0
    page_errors = sum(bool(page["error"]) for page in source_pages)
    unavailable_references = sum(image["preservation_status"] == "unavailable" for image in image_results)
    archived_references = sum(bool(image.get("object_path")) for image in image_results)
    distinct_objects = len({image["sha256"] for image in image_results if image.get("sha256")})
    manifest = {
        "schema_version": 2,
        "created_at": utc_now(),
        "private_archive": True,
        "publication_cleared": False,
        "licensing_status": "unresolved-all-rights-reserved",
        "originals_modified": False,
        "attached_to_drafts": False,
        "human_readable_views": ["by-article", "by-site"],
        "naming_policy": "Use the source caption, title, alt text, or original filename when available; otherwise combine the article slug with the untouched source filename. Never invent visual context.",
        "owner": source_export["owner"],
        "source_occurrence_count": len(source_records),
        "unique_source_page_count": len(source_pages),
        "failed_source_page_count": page_errors,
        "unique_image_reference_count": len(image_results),
        "archived_image_reference_count": archived_references,
        "unavailable_image_reference_count": unavailable_references,
        "distinct_stored_image_object_count": distinct_objects,
        "human_readable_alias_count": alias_count,
        "source_pages": source_pages,
        "images": image_results,
    }

    if args.apply:
        timestamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
        manifest_path = archive_root / "manifests" / f"{timestamp}.json"
        if manifest_path.exists():
            manifest_path = archive_root / "manifests" / f"{timestamp}-{os.getpid()}.json"
        write_json_atomic(manifest_path, manifest)
        write_json_atomic(archive_root / "manifests/latest.json", manifest)
        readme = """Hondabase private source-image preservation archive

objects/sha256/  Immutable, content-addressed original bytes.
by-article/      Human-readable hard links grouped by draft, source site, and source page.
by-site/         Site-wide assets kept out of individual article folders.
manifests/       URL, caption, original-name, checksum, dimensions, and licensing provenance.

The files are unmodified and are not cleared for publication. They are not attached to drafts
or stored in the public articles repository. A human-readable filename is based only on source
metadata; index.json always retains the exact original filename and URL.
"""
        (archive_root / "README.txt").write_text(readme, encoding="utf-8")
        os.chmod(archive_root / "README.txt", 0o640)
        print(f"Manifest: {manifest_path}", flush=True)

    print(f"Archived references: {archived_references}", flush=True)
    print(f"Unavailable references without a preserved variant: {unavailable_references}", flush=True)
    print(f"Distinct image objects: {distinct_objects}", flush=True)
    print(f"Human-readable aliases: {alias_count}", flush=True)
    return 0 if page_errors == 0 else 2


if __name__ == "__main__":
    raise SystemExit(main())

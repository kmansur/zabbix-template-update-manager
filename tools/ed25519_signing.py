from __future__ import annotations

import base64
import hashlib
import json
import os
from pathlib import Path
from typing import Any

from nacl.signing import SigningKey


def signing_secret_from_env() -> bytes | None:
    encoded = os.environ.get("ZTUM_INDEX_SIGNING_SECRET_KEY_B64", "").strip()
    if not encoded:
        return None
    try:
        raw = base64.b64decode(encoded, validate=True)
    except Exception as exc:
        raise RuntimeError("ZTUM_INDEX_SIGNING_SECRET_KEY_B64 is not valid base64") from exc
    if len(raw) not in (32, 64):
        raise RuntimeError("Ed25519 signing secret must be a 32-byte seed or 64-byte libsodium secret key")
    return raw[:32]


def sign_document(content: bytes, secret: bytes) -> dict[str, Any]:
    signing_key = SigningKey(secret)
    public_key = bytes(signing_key.verify_key)
    key_id = "ed25519:" + hashlib.sha256(public_key).hexdigest()[:24]
    signature = signing_key.sign(content).signature
    return {
        "schema_version": 1,
        "algorithm": "ed25519",
        "key_id": key_id,
        "content_sha256": hashlib.sha256(content).hexdigest(),
        "signature_b64": base64.b64encode(signature).decode("ascii"),
    }


def write_signature(path: Path, content: bytes, secret: bytes) -> dict[str, Any]:
    document = sign_document(content, secret)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(document, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    return document

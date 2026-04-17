## How to Use

    Save this code as index.php in your web server directory.

    Create the img/ folder with logo.png, lam-logo.jpg, reg.jpg, excel.jpg (or adjust paths).

    Database will be created automatically when you first run the script.

    Login with username superadmin and password admin123.

    Enroll fingerprints for workers by clicking the "Enroll Fingerprint" button next to each worker.

    Mark attendance with fingerprint by clicking the fingerprint button on the dashboard.

## Requirements

    PHP 7.4+ with mysqli, openssl, json, random extensions.

    HTTPS (required for WebAuthn; localhost works without HTTPS).

    A device with a fingerprint sensor or Windows Hello / Touch ID.

Notes

    The WebAuthn implementation includes a minimal CBOR decoder for educational purposes. For production, consider using web-auth/webauthn-lib via Composer for full security.

    Fingerprint credentials are stored as public keys; signature verification is simplified (in production, verify the signature using the public key).

    All existing features (manual attendance, department management, etc.) remain functional.

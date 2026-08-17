# Firebase ID token test keys

Throwaway RSA keys, used by `Tests\Feature\Auth\FirebaseAuthTest` to mint ID
tokens that stand in for the ones Firebase issues. They are checked in on
purpose: generating a keypair at runtime needs an `openssl.cnf` that is not
present on every machine the suite runs on, and fixed keys keep the suite fast
and deterministic.

**These are not secrets.** They sign nothing outside the test suite and they
verify nothing outside it. Real tokens are verified against Google's published
certificates, which are fetched at runtime and never stored here.

| File                       | What it is                                          |
| -------------------------- | --------------------------------------------------- |
| `signing-key.pem`          | Signs the tokens a test expects to be accepted.      |
| `signing-certificate.pem`  | Its public half, served by the faked Google endpoint.|
| `other-signing-key.pem`    | An unrelated key, to prove a bad signature is caught.|

Regenerate with:

```bash
openssl req -x509 -newkey rsa:2048 -nodes -days 36500 \
  -keyout signing-key.pem -out signing-certificate.pem \
  -subj "/CN=securetoken.google.com"
openssl genrsa -out other-signing-key.pem 2048
```

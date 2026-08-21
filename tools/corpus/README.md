# Real-project corpus harness

This repository-only tool projects existing Laravel source files into disposable
synthetic Modules, then runs the real Moduark source index and independent
token-based precision, line-anchoring, and literal-Facade recall oracles. It
does not modify or boot the target application.

```bash
php tools/corpus/run.php \
  --manifest=tools/corpus/manifests/firefly-iii.json \
  --root=/path/to/firefly-iii \
  --output=/tmp/firefly-iii-moduark.json
```

The public manifest pins the reviewed Firefly III revision. The generic local
manifest intentionally contains no private repository identity or source data.
Reports normalize source locations relative to the corpus root; do not commit a
private report without reviewing its contents.

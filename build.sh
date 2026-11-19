#!/bin/sh
# Builds ci-pack-pricing.zip (folder ci-pack-pricing/) for a GitHub release.
set -e
cd "$(dirname "$0")"
rm -rf /tmp/cipp-build && mkdir -p /tmp/cipp-build/ci-pack-pricing
cp -r ci-pack-pricing.php lib README.md CHANGELOG.md /tmp/cipp-build/ci-pack-pricing/
(cd /tmp/cipp-build && zip -qr ci-pack-pricing.zip ci-pack-pricing)
mv /tmp/cipp-build/ci-pack-pricing.zip ./ci-pack-pricing.zip
echo "built ci-pack-pricing.zip"

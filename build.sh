#!/usr/bin/env bash

zip -r build/twispay_payments-1.0.1.zip . -x '.git/*' -x '.idea/*' -x '*.iml' -x .DS_Store -x 'build/' -x .gitignore -x .editorconfig -x build.sh

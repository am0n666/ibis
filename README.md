# ibis UI

This repository provides a simple PHP-based web interface for running the [ibis-next](https://github.com/Hi-Folks/ibis-next) command-line tool. Upload a zipped collection of markdown files, edit your `ibis.php` configuration and generate PDF, EPUB or HTML books directly from the browser.

## Installation

1. Install PHP and Composer.
2. Install `ibis-next` globally:

```bash
composer global require hi-folks/ibis-next
```

Ensure Composer's global `vendor/bin` directory is on your `PATH` so the `ibis-next` command is available.

## Features

* Import or export your `ibis.php` configuration.
* Upload additional styles, covers and fonts under `assets/`.
* Choose among uploaded assets when running `ibis-next`.

The interface is contained in `public/index.php` and relies on Bootstrap for basic styling.


# Others (Advanced / Legacy-Compatible Examples)

This folder keeps examples that are still useful but are not part of the main provider matrices in `examples/text` and `examples/image`.

## Notes

- `01-basic-text-generation.php` and `02-image-generation.php` were removed from this folder because they duplicated main flows.
- Their detailed educational versions now live in:
  - `examples/text/00-detailed-basic-text-generation.php`
  - `examples/image/00-detailed-image-generation.php`

## Runnable advanced examples

- `03-text-to-speech.php`
- `04-audio-transcription.php`
- `05-multimodal-pipelines.php`
- `06-streaming-responses.php`
- `07-tool-calling.php`
- `08-structured-output.php`
- `09-middleware-pipeline.php`
- `10-pricing-and-cost-tracking.php`
- `11-observability-events.php`
- `12-custom-driver-injection.php`
- `13-embeddings.php`
- `14-error-handling.php`

All runnable scripts in this folder now use `examples/_common.php`, so they support:

- environment variable loading from `.env`
- shared HTTP/bootstrap setup
- pricing middleware by default

## Integration references (framework-oriented)

- `15-laravel-integration.php`
- `16-symfony-integration.php`
- `17-cakephp-integration.php`
- `18-dependency-injection-container.php`

These are architecture/reference examples and may require adapting to your real app container/framework bootstrap.

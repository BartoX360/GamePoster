<?php
use App\Models\{Plugin, Device, DeviceModel, User};
use App\Services\{PluginImportService, ImageGenerationService};
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;

Plugin::where('name', 'GamePoster (test)')->get()->each->delete();
$file = new UploadedFile('/tmp/gameposter.zip', 'gameposter.zip', 'application/zip', null, true);
$p = Plugin::find(app(PluginImportService::class)->importFromZip($file, User::find(1))->id);
$base = ['platforms' => ['ps5', 'switch2', 'pc']];
$p->update(['name' => 'GamePoster (test)', 'configuration' => $base + ['style' => 'eink', 'language' => 'en', 'featured' => 'rotate']]);
echo "url=", $p->resolveLiquidVariables($p->polling_url), "\n";

// Flux Game Watch figé du 19 octobre 2026
$feed = json_decode(file_get_contents('/tmp/test_payload.json'), true);
$noimg = $feed; $noimg['upcoming'][0]['cover_url'] = null; $noimg['upcoming'][0]['banner_url'] = null;
$empty = ['year' => 2026, 'status' => 'ok', 'stale' => false, 'hall' => [], 'recent' => [], 'upcoming' => []];
// heure UTC du 18/10 dont le créneau horaire vaut $k modulo 9 (rotation : rubrique = k % 3, rang = k / 3)
$slot = fn($k) => (int) collect(range(0, 23))->first(fn($h) => intdiv(Carbon::create(2026, 10, 19, $h, 0, 0, 'UTC')->timestamp, 3600) % 9 == $k);
// les formats réduits ne sont rendus qu'en e-ink : le mashup de l'écran couleur sort blanc dans LaraPaper (WeatherPoster aussi)
$cases = [
  // nom, taille, modèle, style, langue, affiche, données, heure (UTC)
  ['fr_color_hall', 'full', 55, 'color', 'fr', 'hall', $feed, 10],
  ['en_eink_hall', 'full', 1, 'eink', 'en', 'hall', $feed, 10],
  ['fr_color_recent', 'full', 55, 'color', 'fr', 'recent', $feed, 10],
  ['fr_eink_recent', 'full', 1, 'eink', 'fr', 'recent', $feed, 10],
  ['fr_color_upcoming', 'full', 55, 'color', 'fr', 'upcoming', $feed, 10],
  ['en_x_upcoming', 'full', 14, 'eink', 'en', 'upcoming', $feed, 10],
  ['en_color_rotate_hall2', 'full', 55, 'color', 'en', 'rotate', $feed, $slot(3)],
  ['fr_color_rotate_up2', 'full', 55, 'color', 'fr', 'rotate', $feed, $slot(5)],
  ['fr_eink_noimg', 'full', 1, 'eink', 'fr', 'upcoming', $noimg, 10],
  ['en_hh', 'half_horizontal', 1, 'eink', 'en', 'hall', $feed, 10],
  ['fr_hv', 'half_vertical', 1, 'eink', 'fr', 'recent', $feed, 10],
  ['en_q', 'quadrant', 1, 'eink', 'en', 'upcoming', $feed, 10],
  ['fr_q', 'quadrant', 1, 'eink', 'fr', 'hall', $feed, 10],
  ['fr_hh', 'half_horizontal', 1, 'eink', 'fr', 'upcoming', $feed, 10],
  ['fr_error', 'full', 1, 'eink', 'fr', 'rotate', ['error' => 'x'], 10],
  ['en_empty', 'full', 55, 'color', 'en', 'rotate', $empty, 10],
];
@mkdir('/tmp/gpr'); array_map('unlink', glob('/tmp/gpr/*.png'));
foreach ($cases as [$name, $size, $mid, $style, $lang, $feat, $payload, $h]) {
  Carbon::setTestNow(Carbon::create(2026, 10, 19, $h, 0, 0, 'UTC'));
  $p->configuration = $base + ['style' => $style, 'language' => $lang, 'featured' => $feat];
  $p->data_payload = $payload; $p->saveQuietly();
  $model = DeviceModel::find($mid);
  $dev = new Device(['name' => 'test']); $dev->setRelation('deviceModel', $model);
  $html = $p->fresh()->render($size, true, $dev);
  if ($name == 'fr_color_hall') file_put_contents('/tmp/gpr/sample.html', $html);
  $pal = $mid == 55 ? App\Models\DevicePalette::find(12) : null;
  $id = ImageGenerationService::generateImageFromModel(markup: $html, deviceModel: $model, palette: $pal);
  copy(storage_path("app/public/images/generated/$id.png"), "/tmp/gpr/$name.png");
  echo "$name ok\n";
}
Carbon::setTestNow();
$p->updateDataPayload();
echo 'live payload: ', substr(json_encode(array_map(fn($g) => $g['title'], $p->fresh()->data_payload['upcoming'] ?? [])), 0, 200), "\n";

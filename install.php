<?php
// Importe la recette comme « GamePoster » avec les plateformes de Game Watch et l'affichage de l'Echo Show
use App\Models\{Plugin, User};
use App\Services\PluginImportService;
use Illuminate\Http\UploadedFile;
Plugin::where('name', 'like', 'GamePoster%')->get()->each->delete();
$file = new UploadedFile('/tmp/gameposter.zip', 'gameposter.zip', 'application/zip', null, true);
$p = Plugin::find(app(PluginImportService::class)->importFromZip($file, User::find(1))->id);
$gw = Plugin::where('name', 'like', 'Game Watch%')->first()?->configuration ?? [];
$p->name = 'GamePoster';
$p->configuration = ['platforms' => $gw['platforms'] ?? [], 'featured' => 'rotate', 'style' => 'color', 'language' => 'fr'];
$p->save();
$p->updateDataPayload();
$d = App\Models\Device::find(2);
$id = App\Services\ImageGenerationService::generateImage($p->fresh()->render('full', true, $d), 2, $p);
copy(storage_path("app/public/images/generated/$id.png"), '/tmp/gp_live.png');
echo "installed id {$p->id}: ", count($p->fresh()->data_payload['hall'] ?? []), " games in hall\n";

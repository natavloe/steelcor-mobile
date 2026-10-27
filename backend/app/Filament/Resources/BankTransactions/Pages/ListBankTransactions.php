<?php

namespace App\Filament\Resources\BankTransactions\Pages;

use App\Filament\Concerns\RunsWorkflow;
use App\Filament\Resources\BankTransactions\BankTransactionResource;
use App\Models\SellerEntity;
use App\Models\StaffUser;
use App\Services\Bank\BankStatementImporter;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListBankTransactions extends ListRecords
{
    use RunsWorkflow;

    protected static string $resource = BankTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')->label('Загрузить выписку')->icon('heroicon-o-arrow-up-tray')
                ->visible(fn () => auth('staff')->user()?->can(Permissions::BANK_IMPORT))
                ->modalDescription('Файл выгрузки из интернет-банка в формате 1С (1CClientBankExchange, обычно kl_to_1c.txt). Повторная загрузка тех же платежей безопасна — дубли пропускаются.')
                ->schema([
                    Select::make('seller_entity_id')->label('Чей расчётный счёт')->options(SellerEntity::query()->pluck('name_short', 'id'))->required(),
                    FileUpload::make('file')->label('Файл выписки')->required()->disk('local')->directory('bank-statements')
                        ->acceptedFileTypes(['text/plain', 'application/octet-stream'])->maxSize(10240),
                ])
                ->action(function (array $data) {
                    $content = Storage::disk('local')->get($data['file']);
                    Storage::disk('local')->delete($data['file']); // данные сохраняются в базе (поле raw), файл не храним
                    $stats = null;
                    static::run(function () use ($data, $content, &$stats) {
                        /** @var StaffUser $staff */
                        $staff = auth('staff')->user();
                        $stats = app(BankStatementImporter::class)->importFile(SellerEntity::findOrFail($data['seller_entity_id']), $content, $staff);
                    }, 'Выписка загружена');
                    if ($stats) {
                        \Filament\Notifications\Notification::make()
                            ->title("Новых: {$stats['created']}, автопривязано: {$stats['matched']}, дублей: {$stats['duplicates']}, чужих счетов: {$stats['skipped']}")
                            ->info()->persistent()->send();
                    }
                }),
        ];
    }
}

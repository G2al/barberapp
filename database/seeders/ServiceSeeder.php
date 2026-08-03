<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $hairServices = [
            ['Piega corta', 10, 'fixed', 30],
            ['Piega lunga', 12, 'fixed', 45],
            ['Piega torchon', 15, 'starting_from', 45],
            ['Piega + ricrescita colore', 28, 'starting_from', 90],
            ['Shatush, sfumature e balayage', 70, 'starting_from', 180],
            ['Colpi di sole', 40, 'starting_from', 150],
            ['Bagno di colore + piega', 18, 'fixed', 75],
            ['Permanente', 40, 'fixed', 120],
            ['Nanoplastia', 120, 'starting_from', 180],
            ['Trattamento liscio perfetto', 70, 'starting_from', 150],
            ['Trattamenti specifici', 5, 'starting_from', 30],
            ['Applicazione extension a progetto', null, 'starting_from', 120, false],
            ['Acconciatura cerimonia', 30, 'fixed', 60],
            ['Pacchetto sposa', 300, 'starting_from', 180],
            ['Taglio', 10, 'fixed', 30],
            ['Colore completo', 35, 'fixed', 90],
            ['Laminazione brasiliana', 10, 'starting_from', 60],
        ];

        $beautyServices = [
            ['Manicure classico', 8, 'fixed', 30],
            ['Applicazione smalto', 5, 'fixed', 20],
            ['Smalto semipermanente', 12, 'fixed', 45],
            ['Semipermanente Comby', 18, 'fixed', 60],
            ['Semipermanente con applicazione', 20, 'starting_from', 75],
            ['Copertura in gel', 25, 'fixed', 90],
            ['Aggiusto unghia', 3, 'fixed', 15],
            ['Rimozione copertura + manicure', 12, 'fixed', 45],
            ['Ricostruzione', 35, 'fixed', 120],
            ['Pedicure', 15, 'fixed', 45],
            ['Pedicure semipermanente', 18, 'fixed', 60],
            ['Baffetto', 3, 'fixed', 15],
            ['Sopracciglia con pinzetta', 5, 'fixed', 15],
            ['Sopracciglia con cera', 4, 'fixed', 15],
            ['Ascelle donna', 5, 'fixed', 15],
            ['Inguine parziale', 7, 'fixed', 20],
            ['Inguine totale', 10, 'fixed', 30],
            ['Cera mento', 5, 'fixed', 15],
            ['Cera lombare', 4, 'fixed', 15],
            ['Gambaletto', 7, 'fixed', 25],
            ['Coscia', 7, 'fixed', 25],
            ['Braccia meta', 6, 'fixed', 20],
            ['Braccia intere', 8, 'fixed', 30],
            ['Addome donna', 5, 'fixed', 20],
            ['Glutei', 5, 'fixed', 20],
            ['Pacchetto cera completa', 32, 'fixed', 90],
            ['Braccia uomo', 10, 'fixed', 30],
            ['Ascella uomo', 6, 'fixed', 20],
            ['Gamba o coscia uomo', 15, 'fixed', 45],
            ['Petto o addome uomo', 15, 'fixed', 45],
            ['Pulizia viso', 25, 'fixed', 60],
            ['Henne', 10, 'fixed', 30],
        ];

        $services = [
            ...$this->prepare($hairServices, 'hair'),
            ...$this->prepare($beautyServices, 'beauty'),
        ];

        Service::query()->update(['is_active' => false]);

        foreach ($services as $serviceData) {
            $service = Service::updateOrCreate(
                ['name' => $serviceData['name']],
                $serviceData
            );

            if (!$service->phases()->exists()) {
                $service->phases()->create([
                    'name' => 'Lavorazione',
                    'duration' => $service->duration,
                    'staff_required' => true,
                    'position' => 1,
                ]);
            }
        }
    }

    private function prepare(array $services, string $department): array
    {
        return array_map(function (array $service) use ($department): array {
            [$name, $price, $priceType, $duration] = $service;

            return [
                'name' => $name,
                'description' => 'Durata provvisoria in attesa della conferma dello staff.',
                'price' => $price,
                'price_type' => $priceType,
                'duration' => $duration,
                'department' => $department,
                'loyalty_points' => 0,
                'is_active' => $service[4] ?? true,
            ];
        }, $services);
    }
}

<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Trade;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Référentiels de la phase 1 (Lomé, mécanique). Idempotent : peut être relancé
 * en production pour ajouter des entrées sans écraser l'activation choisie
 * dans le back-office.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLome();
        $this->seedMechanics();
        $this->seedVehicleTypes();
    }

    private function seedLome(): void
    {
        $city = City::firstOrCreate(
            ['slug' => 'lome'],
            ['name' => 'Lomé', 'country_code' => 'TG', 'latitude' => 6.1319, 'longitude' => 1.2228, 'is_active' => true],
        );

        foreach ($this->lomeDistricts() as [$name, $latitude, $longitude]) {
            $city->districts()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'latitude' => $latitude, 'longitude' => $longitude],
            );
        }
    }

    private function seedMechanics(): void
    {
        $trade = Trade::firstOrCreate(
            ['slug' => 'mecanique'],
            ['name' => 'Mécanique', 'position' => 1, 'is_active' => true],
        );

        $services = [
            ['vidange', 'Vidange et entretien', 'oil_barrel'],
            ['moteur', 'Mécanique moteur', 'build'],
            ['freins', 'Freins', 'disc_full'],
            ['embrayage-boite', 'Embrayage et boîte de vitesses', 'settings'],
            ['suspension-direction', 'Suspension et direction', 'tune'],
            ['electricite', 'Électricité et batterie', 'electrical_services'],
            ['diagnostic', 'Diagnostic électronique', 'troubleshoot'],
            ['pneus', 'Pneus et vulcanisation', 'tire_repair'],
            ['climatisation', 'Climatisation', 'ac_unit'],
            ['carrosserie', 'Carrosserie et tôlerie', 'car_crash'],
            ['peinture', 'Peinture', 'format_paint'],
            ['soudure', 'Soudure', 'construction'],
            ['depannage', 'Dépannage et remorquage', 'emergency'],
        ];

        foreach ($services as $position => [$slug, $name, $icon]) {
            $trade->services()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'icon' => $icon, 'position' => $position + 1],
            );
        }
    }

    private function seedVehicleTypes(): void
    {
        $types = [
            ['voiture', 'Voiture', 'directions_car'],
            ['moto', 'Moto', 'two_wheeler'],
            ['tricycle', 'Tricycle', 'electric_rickshaw'],
            ['poids-lourd', 'Poids lourd', 'local_shipping'],
        ];

        foreach ($types as $position => [$slug, $name, $icon]) {
            VehicleType::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'icon' => $icon, 'position' => $position + 1],
            );
        }
    }

    /**
     * Quartiers de Lomé et de sa périphérie, avec leur centre.
     * Coordonnées : © contributeurs OpenStreetMap (ODbL), extraites en octobre 2026.
     *
     * @return list<array{string, float, float}>
     */
    private function lomeDistricts(): array
    {
        return [
            ['Ablogamé', 6.14001, 1.26247],
            ['Abobo', 6.23431, 1.37573],
            ['Abobokomé', 6.13475, 1.23057],
            ['Abové', 6.15647, 1.20496],
            ['Adakpamé', 6.17532, 1.28972],
            ['Adamavo', 6.16928, 1.3114],
            ['Adawlato', 6.12532, 1.22751],
            ['Adidogomé', 6.21014, 1.15475],
            ['Adoboukomé', 6.13482, 1.22603],
            ['Aflao-Gakli', 6.17861, 1.19913],
            ['Agbadahonou', 6.12513, 1.22421],
            ['Agbalépédogan', 6.1919, 1.1996],
            ['Agoè-Assiyéyé', 6.23254, 1.19745],
            ['Agoè-Démakpoè', 6.24837, 1.20047],
            ['Agoè-Kitidjan', 6.22062, 1.21712],
            ['Agoè-Nyivé', 6.22426, 1.21017],
            ['Agovémé', 6.28802, 1.15588],
            ['Aguia Komé', 6.13195, 1.23128],
            ['Akakopé', 6.24888, 1.27803],
            ['Akodésséwa', 6.15364, 1.26734],
            ['Akodésséwa-Kponou', 6.1664, 1.28668],
            ['Akodésséwa-Kpota', 6.1642, 1.27981],
            ['Akossombo', 6.16319, 1.20278],
            ['Amoutivé', 6.14177, 1.23062],
            ['Anfamé', 6.17024, 1.27314],
            ['Anthonio-Nétimé', 6.1313, 1.24291],
            ['Attiégou', 6.19321, 1.26487],
            ['Avénou', 6.17317, 1.19144],
            ['Avépozo', 6.168, 1.343],
            ['Aveta', 6.27249, 1.2894],
            ['Baguida', 6.16977, 1.32521],
            ['Baguida-Dévégo', 6.19002, 1.34907],
            ['Bassadji', 6.14282, 1.23633],
            ['Bè', 6.14266, 1.24851],
            ['Bè-Ahligo', 6.13956, 1.25479],
            ['Bè-Apéhémé', 6.14469, 1.23999],
            ['Bè-Hédjé', 6.1514, 1.25675],
            ['Bè-Klikamé', 6.16792, 1.20595],
            ['Bè-Kpota', 6.16241, 1.25923],
            ['Béniglato', 6.12835, 1.2341],
            ['Cacaveli', 6.21489, 1.20027],
            ['Cassablanca', 6.15703, 1.20024],
            ['Cité de Djagblé', 6.27546, 1.25243],
            ['Dévégo', 6.19396, 1.34463],
            ['Dogbéavou', 6.15219, 1.21478],
            ['Doulassamé', 6.14022, 1.22704],
            ['Doumasséssé', 6.16073, 1.2176],
            ['Fréau-Jardin', 6.13157, 1.22402],
            ['Gbama Kopé', 6.27111, 1.2656],
            ['Gbényédji', 6.1458, 1.25566],
            ['Gbonvié', 6.15457, 1.22148],
            ['Hanoukopé', 6.13784, 1.2186],
            ['Hédzranawoé', 6.18497, 1.24841],
            ['Kagnikopé', 6.16541, 1.29879],
            ['Kélégougan', 6.19814, 1.24368],
            ['Klémé', 6.21208, 1.11237],
            ['Kodjoviakopé', 6.12004, 1.20526],
            ['Kohe', 6.21843, 1.13825],
            ['Kokétimé', 6.12763, 1.22383],
            ['Kotokou-Kondji', 6.13562, 1.25474],
            ['Kpéhénou', 6.13854, 1.23733],
            ['Kpogan', 6.18546, 1.37649],
            ['Kpogédé', 6.24841, 1.37785],
            ['Lom-Nava', 6.1391, 1.23318],
            ['Lomé-2', 6.1895, 1.22514],
            ['Lowe', 6.28665, 1.28076],
            ['Minamadou', 6.2251, 1.18658],
            ['N\'tifafakomé', 6.14949, 1.23733],
            ['Nyékonakpoé', 6.13015, 1.2059],
            ['Octaviano-Nétimé', 6.1351, 1.21373],
            ['Quartier Administratif', 6.1261, 1.21626],
            ['Résidence du Bénin', 6.17298, 1.22409],
            ['Sagbado', 6.19028, 1.13661],
            ['Saint-Joseph', 6.15361, 1.23315],
            ['Sanguera', 6.2338, 1.10824],
            ['Souza-Nétimé', 6.13671, 1.24161],
            ['Soviépé', 6.1751, 1.18221],
            ['Todokpoui', 6.29724, 1.28265],
            ['Tokoin-Aéroport', 6.17029, 1.25625],
            ['Tokoin-Élavagnon', 6.14758, 1.22611],
            ['Tokoin-Forever', 6.15893, 1.22983],
            ['Tokoin-Gbadago', 6.14597, 1.21606],
            ['Tokoin-Hôpital', 6.1445, 1.20921],
            ['Tokoin-Lycée', 6.15207, 1.22507],
            ['Tokoin-N\'kafu', 6.15695, 1.2382],
            ['Tokoin-Ouest', 6.1442, 1.20274],
            ['Tokoin-Solidarité', 6.15186, 1.2056],
            ['Tokoin-Tamé', 6.16428, 1.22578],
            ['Tokoin-Wuiti', 6.16692, 1.23616],
            ['Totsi', 6.18778, 1.18571],
            ['Vakpossito', 6.2189, 1.15924],
            ['Wété', 6.14719, 1.26049],
            ['Wétrivi-Kondji', 6.13435, 1.22142],
            ['Zone Portuaire', 6.14806, 1.28565],
        ];
    }
}

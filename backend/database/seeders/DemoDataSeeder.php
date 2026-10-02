<?php

namespace Database\Seeders;

use App\Domain\Gis\GeoMath;
use App\Domain\Gis\GisEngine;
use App\Domain\Gis\LineString;
use App\Domain\Gis\RoadGeometryService;
use App\Enums\RoleCode;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Contract;
use App\Models\Contractor;
use App\Models\ContractRoadSection;
use App\Models\District;
use App\Models\Division;
use App\Models\GisFeature;
use App\Models\ResponsibilityAssignment;
use App\Models\Road;
use App\Models\RoadCategory;
use App\Models\RoadSection;
use App\Models\Role;
use App\Models\SubDivision;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Development/demo dataset (§60). EVERY row is flagged is_test = true.
 *
 * Road geometry is synthetic (deterministic random walk), not surveyed alignment.
 * Scenarios covered:
 *  - roads 1–14: active maintenance contract  → contractor workflow
 *  - roads 15–17: maintenance expired         → department workflow
 *  - roads 18–20: no contract                 → department workflow
 *  - road 1: previous (expired) contract with another contractor (contract history)
 *  - road 2: split between two contractors by section
 *  - road 1 / S01: JE changed on 01-Jul-2026 (responsibility history)
 */
class DemoDataSeeder extends Seeder
{
    private const SECTION_LENGTH_M = 10000;

    private const RESPONSIBILITY_FROM = '2025-04-01';

    private string $password;

    /** @var array<string, int> */
    private array $roleIds;

    public function __construct(
        private readonly RoadGeometryService $geometry,
        private readonly GisEngine $gis,
    ) {}

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must not be seeded in production.');
        }

        mt_srand(2026);
        $this->password = Hash::make(env('SEED_USER_PASSWORD', 'Rcd@Demo2026'));
        $this->roleIds = Role::pluck('id', 'code')->all();

        $admins = $this->seedAdmins();
        $org = $this->seedOrganisation();
        $people = $this->seedEngineers($org);
        $contractors = $this->seedContractors();
        $roads = $this->seedRoads($org);
        $this->seedResponsibility($roads, $people, $admins['admin']);
        $this->seedContracts($roads, $contractors);
        $this->seedOtherUsers();
    }

    private function user(string $name, ?string $email, ?string $mobile, RoleCode $role, array $extra = []): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'mobile' => $mobile,
            'password' => $this->password,
            'password_changed_at' => now(),
            'email_verified_at' => now(),
            'mobile_verified_at' => $mobile ? now() : null,
            'status' => User::STATUS_ACTIVE,
            'is_test' => true,
            ...$extra,
        ]);
        $user->roles()->attach($this->roleIds[$role->value]);

        return $user;
    }

    /** @return array{super: User, admin: User} */
    private function seedAdmins(): array
    {
        return [
            'super' => $this->user('Super Admin (Demo)', 'superadmin@rcd.test', '9000000001', RoleCode::SUPER_ADMIN, ['designation' => 'System Administrator']),
            'admin' => $this->user('Admin (Demo)', 'admin@rcd.test', '9000000002', RoleCode::ADMIN, ['designation' => 'MIS Administrator']),
        ];
    }

    /** @return list<array{division: Division, centre: array{0: float, 1: float}, subs: list<array{sub: SubDivision, centre: array{0: float, 1: float}}>}> */
    private function seedOrganisation(): array
    {
        $plan = [
            ['DN', 'North Road Division (Demo)', 'DST-N', 'Demo District North', [23.36, 85.33], [['SDN1', 'North Sub-Division 1', [23.42, 85.20]], ['SDN2', 'North Sub-Division 2', [23.30, 85.47]]]],
            ['DS', 'South Road Division (Demo)', 'DST-S', 'Demo District South', [23.80, 86.43], [['SDS1', 'South Sub-Division 1', [23.86, 86.30]], ['SDS2', 'South Sub-Division 2', [23.74, 86.56]]]],
        ];

        $out = [];
        foreach ($plan as [$code, $name, $dCode, $dName, $centre, $subs]) {
            $district = District::create(['code' => $dCode, 'name' => $dName]);
            $division = Division::create(['code' => $code, 'name' => $name, 'district_id' => $district->id, 'is_test' => true]);

            $subRows = [];
            foreach ($subs as [$sCode, $sName, $sCentre]) {
                $subRows[] = [
                    'sub' => SubDivision::create(['division_id' => $division->id, 'code' => $sCode, 'name' => $sName.' (Demo)', 'district_id' => $district->id, 'is_test' => true]),
                    'centre' => $sCentre,
                ];
            }
            $out[] = ['division' => $division, 'centre' => $centre, 'subs' => $subRows];
        }

        return $out;
    }

    /**
     * One EE per division, one AE per sub-division, two JEs per sub-division, plus
     * two spare JEs for delegation demos.
     *
     * @return array{ee: array<int, User>, ae: array<int, User>, je: array<int, list<User>>, spare_je: list<User>}
     */
    private function seedEngineers(array $org): array
    {
        $people = ['ee' => [], 'ae' => [], 'je' => [], 'spare_je' => []];
        $jeNo = 1;
        $mobile = 9100000001;

        foreach ($org as $d => $row) {
            $division = $row['division'];
            $people['ee'][$division->id] = $this->user("EE {$division->code} (Demo)", 'ee.'.strtolower($division->code).'@rcd.test', (string) $mobile++, RoleCode::EE, ['employee_code' => 'EE-'.$division->code, 'designation' => 'Executive Engineer']);

            foreach ($row['subs'] as $s) {
                $sub = $s['sub'];
                $people['ae'][$sub->id] = $this->user("AE {$sub->code} (Demo)", 'ae.'.strtolower($sub->code).'@rcd.test', (string) $mobile++, RoleCode::AE, ['employee_code' => 'AE-'.$sub->code, 'designation' => 'Assistant Engineer']);

                for ($i = 0; $i < 2; $i++, $jeNo++) {
                    $code = sprintf('JE%03d', $jeNo);
                    $people['je'][$sub->id][] = $this->user("{$code} (Demo)", strtolower($code).'@rcd.test', (string) $mobile++, RoleCode::JE, ['employee_code' => $code, 'designation' => 'Junior Engineer']);
                }
            }
        }

        foreach ([9, 10] as $n) {
            $code = sprintf('JE%03d', $n);
            $people['spare_je'][] = $this->user("{$code} (Demo, reserve)", strtolower($code).'@rcd.test', (string) $mobile++, RoleCode::JE, ['employee_code' => $code, 'designation' => 'Junior Engineer']);
        }

        return $people;
    }

    /** @return list<Contractor> */
    private function seedContractors(): array
    {
        $names = ['Alpha Infra Builders', 'Bharat Road Works', 'Crest Constructions', 'Delta Highways', 'Everest Engineering Co.', 'Falcon Civil Works'];
        $out = [];

        foreach ($names as $i => $name) {
            $n = $i + 1;
            $contractor = Contractor::create([
                'code' => sprintf('CTR%03d', $n),
                'name' => "{$name} (Demo)",
                'registration_no' => sprintf('REG/DEMO/%04d', $n),
                'contact_person' => "Contact Person {$n}",
                'mobile' => (string) (9200000000 + $n),
                'email' => "contractor{$n}@rcd.test",
                'address' => "Demo Address {$n}",
                'is_test' => true,
            ]);
            $this->user("{$name} — Site Manager (Demo)", "contractor{$n}@rcd.test", (string) (9300000000 + $n), RoleCode::CONTRACTOR, ['contractor_id' => $contractor->id, 'designation' => 'Site Manager']);
            $out[] = $contractor;
        }

        return $out;
    }

    /** @return list<array{road: Road, sub: SubDivision, division: Division}> */
    private function seedRoads(array $org): array
    {
        $categories = RoadCategory::pluck('id', 'code');
        $categoryCycle = ['SH', 'MDR', 'MDR', 'ODR', 'ODR'];
        $assetTypes = AssetType::pluck('id', 'code');
        $roads = [];
        $no = 1;

        foreach ($org as $row) {
            foreach ($row['subs'] as $s) {
                for ($i = 0; $i < 5; $i++, $no++) {
                    $lengthM = mt_rand(12, 36) * 1000;
                    $line = $this->randomRoadLine($s['centre'], $lengthM);
                    $code = sprintf('RCD-%03d', $no);

                    $road = Road::create([
                        'code' => $code,
                        'name' => "Demo Road {$no} ({$s['sub']->code})",
                        'road_number' => sprintf('DR-%02d', $no),
                        'road_category_id' => $categories[$categoryCycle[$i]],
                        'division_id' => $row['division']->id,
                        'sub_division_id' => $s['sub']->id,
                        'district_id' => $row['division']->district_id,
                        'start_location' => "Demo Village {$no}A",
                        'end_location' => "Demo Village {$no}B",
                        'start_chainage_m' => 0,
                        'end_chainage_m' => 0, // set from geometry
                        'status' => 'active',
                        'description' => 'Synthetic demo alignment — not surveyed.',
                        'is_test' => true,
                    ]);

                    $this->geometry->saveRoadGeometry($road, $line, 'imported', 'Demo seed geometry');
                    $road->refresh();

                    for ($from = 0, $k = 1; $from < $road->end_chainage_m; $from += self::SECTION_LENGTH_M, $k++) {
                        $to = min($from + self::SECTION_LENGTH_M, $road->end_chainage_m);
                        $section = RoadSection::create([
                            'road_id' => $road->id,
                            'code' => sprintf('S%02d', $k),
                            'name' => sprintf('%s km %.1f–%.1f', $code, $from / 1000, $to / 1000),
                            'division_id' => $row['division']->id,
                            'sub_division_id' => $s['sub']->id,
                            'start_chainage_m' => $from,
                            'end_chainage_m' => $to,
                            'length_m' => $to - $from,
                            'status' => 'active',
                            'is_test' => true,
                        ]);
                        $this->geometry->cutSection($road, $section, $line);
                    }

                    $this->seedAssets($road, $line, $assetTypes->all());
                    $roads[] = ['road' => $road, 'sub' => $s['sub'], 'division' => $row['division']];
                }
            }
        }

        return $roads;
    }

    /** Smooth random walk of 250 m steps from a point near the sub-division centre. */
    private function randomRoadLine(array $centre, int $lengthM): LineString
    {
        $lat = $centre[0] + (mt_rand(-1000, 1000) / 1000) * 0.12;
        $lng = $centre[1] + (mt_rand(-1000, 1000) / 1000) * 0.12;
        $heading = mt_rand(0, 359);
        $turn = 0.0;
        $coords = [[$lng, $lat]];
        $step = 250;

        for ($walked = 0; $walked < $lengthM + $step; $walked += $step) {
            $turn = 0.7 * $turn + 0.3 * mt_rand(-12, 12);
            $heading += $turn;
            $dLat = rad2deg(($step * cos(deg2rad($heading))) / GeoMath::EARTH_RADIUS_M);
            $dLng = rad2deg(($step * sin(deg2rad($heading))) / (GeoMath::EARTH_RADIUS_M * cos(deg2rad($lat))));
            $lat += $dLat;
            $lng += $dLng;
            $coords[] = [$lng, $lat];
        }

        $line = new LineString($coords);

        return $line->slice(0, $lengthM);
    }

    private function seedAssets(Road $road, LineString $line, array $assetTypes): void
    {
        $plan = [['BRIDGE', 'Bridge'], ['CULVERT', 'Culvert'], ['CULVERT', 'Culvert'], ['GUARD_WALL', 'Guard Wall']];

        foreach ($plan as $i => [$type, $label]) {
            $chainage = mt_rand(1, max(2, intdiv($road->end_chainage_m, 1000) - 1)) * 1000 + mt_rand(0, 999);
            [$lng, $lat] = $line->pointAt($chainage - $road->start_chainage_m);
            $section = RoadSection::where('road_id', $road->id)
                ->where('start_chainage_m', '<=', $chainage)->where('end_chainage_m', '>=', $chainage)->first();

            $asset = Asset::create([
                'code' => sprintf('%s-%s-%02d', $road->code, $type, $i + 1),
                'asset_type_id' => $assetTypes[$type],
                'name' => sprintf('%s at km %.3f', $label, $chainage / 1000),
                'road_id' => $road->id,
                'road_section_id' => $section?->id,
                'chainage_m' => $chainage,
                'latitude' => round($lat, 7),
                'longitude' => round($lng, 7),
                'geojson' => json_encode(['type' => 'Point', 'coordinates' => [round($lng, 7), round($lat, 7)]]),
                'status' => 'active',
                'is_test' => true,
            ]);
            $this->gis->indexPoint(GisFeature::TYPE_ASSET, $asset->id, $lat, $lng);
        }
    }

    private function seedResponsibility(array $roads, array $people, User $admin): void
    {
        foreach ($roads as $idx => ['road' => $road, 'sub' => $sub, 'division' => $division]) {
            $je = $people['je'][$sub->id][$idx % 2];

            foreach ($road->sections()->get() as $section) {
                $base = ['scope_type' => ResponsibilityAssignment::SCOPE_SECTION, 'scope_id' => $section->id, 'is_test' => true, 'created_by' => $admin->id];

                if ($idx === 0 && $section->code === 'S01') {
                    // History example from the requirement: JE changed on 01-Jul-2026.
                    $other = $people['je'][$sub->id][1];
                    ResponsibilityAssignment::create($base + ['role_code' => RoleCode::JE, 'user_id' => $je->id, 'effective_from' => self::RESPONSIBILITY_FROM, 'effective_to' => '2026-06-30', 'ended_by' => $admin->id, 'remarks' => 'Transferred']);
                    ResponsibilityAssignment::create($base + ['role_code' => RoleCode::JE, 'user_id' => $other->id, 'effective_from' => '2026-07-01']);
                } else {
                    ResponsibilityAssignment::create($base + ['role_code' => RoleCode::JE, 'user_id' => $je->id, 'effective_from' => self::RESPONSIBILITY_FROM]);
                }

                ResponsibilityAssignment::create($base + ['role_code' => RoleCode::AE, 'user_id' => $people['ae'][$sub->id]->id, 'effective_from' => self::RESPONSIBILITY_FROM]);
                ResponsibilityAssignment::create($base + ['role_code' => RoleCode::EE, 'user_id' => $people['ee'][$division->id]->id, 'effective_from' => self::RESPONSIBILITY_FROM]);
            }
        }
    }

    private function seedContracts(array $roads, array $contractors): void
    {
        $seq = 1;
        $make = function (Contractor $contractor, Road $road, string $mStart, string $mEnd, string $status = Contract::STATUS_ACTIVE) use (&$seq) {
            $start = Carbon::parse($mStart)->subYear();
            $n = $seq++;

            return Contract::create([
                'contract_no' => sprintf('RCD/MNT/DEMO/%03d', $n),
                'name' => "Maintenance of {$road->code}",
                'contractor_id' => $contractor->id,
                'division_id' => $road->division_id,
                'agreement_no' => sprintf('AGR/DEMO/%03d', $n),
                'agreement_date' => $start->copy()->subMonth(),
                'work_order_date' => $start->copy()->subDays(10),
                'start_date' => $start,
                'end_date' => Carbon::parse($mStart)->subDay(),
                'maintenance_start_date' => $mStart,
                'maintenance_end_date' => $mEnd,
                'contract_value' => mt_rand(150, 900) * 100000,
                'status' => $status,
                'remarks' => 'Demo contract',
                'is_test' => true,
            ]);
        };
        $map = function (Contract $contract, Road $road, ?RoadSection $section, int $from, int $to, string $effFrom, ?string $effTo) {
            ContractRoadSection::create([
                'contract_id' => $contract->id,
                'road_id' => $road->id,
                'road_section_id' => $section?->id,
                'start_chainage_m' => $from,
                'end_chainage_m' => $to,
                'effective_from' => $effFrom,
                'effective_to' => $effTo,
                'status' => ContractRoadSection::STATUS_ACTIVE,
                'is_test' => true,
            ]);
        };

        foreach ($roads as $idx => ['road' => $road]) {
            $n = $idx + 1;
            $contractor = $contractors[$idx % count($contractors)];

            if ($n <= 14) {
                if ($n === 1) {
                    // Contract history: earlier contractor whose maintenance ended in 2024.
                    $old = $make($contractors[5], $road, '2021-04-01', '2024-03-31', 'completed');
                    $map($old, $road, null, $road->start_chainage_m, $road->end_chainage_m, '2021-04-01', '2024-03-31');
                }

                $mStart = $n % 2 ? '2025-04-01' : '2025-10-01';
                $mEnd = $n % 3 ? '2028-03-31' : '2027-09-30';

                if ($n === 2) {
                    // Split coverage: last section maintained by a different contractor.
                    $sections = $road->sections()->get();
                    $last = $sections->last();
                    $c1 = $make($contractor, $road, $mStart, $mEnd);
                    $c2 = $make($contractors[($idx + 1) % count($contractors)], $road, $mStart, $mEnd);
                    foreach ($sections as $section) {
                        $map($section->is($last) ? $c2 : $c1, $road, $section, $section->start_chainage_m, $section->end_chainage_m, $mStart, null);
                    }

                    continue;
                }

                $contract = $make($contractor, $road, $mStart, $mEnd);
                $map($contract, $road, null, $road->start_chainage_m, $road->end_chainage_m, $mStart, null);
            } elseif ($n <= 17) {
                // Maintenance period expired → department flow.
                $contract = $make($contractor, $road, '2023-04-01', '2026-03-31', 'completed');
                $map($contract, $road, null, $road->start_chainage_m, $road->end_chainage_m, '2023-04-01', '2026-03-31');
            }
            // Roads 18–20: no contract.
        }
    }

    private function seedOtherUsers(): void
    {
        $this->user('RCD Staff 1 (Demo)', 'staff1@rcd.test', '9400000001', RoleCode::RCD_STAFF, ['designation' => 'Work Inspector']);
        foreach ([1, 2, 3] as $n) {
            $this->user("Citizen {$n} (Demo)", null, (string) (9500000000 + $n), RoleCode::CITIZEN);
        }
    }
}

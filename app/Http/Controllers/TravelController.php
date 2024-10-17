<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;

class TravelController extends Controller
{
    #入力値からgooglemapのルートを作成する
    public function generateTravelPlan(Request $request):JsonResponse
    {
        // TODOバリデーション

        /** @var string $region */
        $region = $request->input('region');
        /** @var array<string> $destinations */
        $destinations = $request->input('destinations', []);

        // 候補地がない場合、自動で地域から一日で回れる地点を選定
        if (empty($destinations)) {
            $destinations = $this->generateDestinationsFromRegion($region);

            if (is_array($destinations) && empty($destinations)) {
                return response()->json(['error' => '候補地の生成に失敗しました。'], 500);
            }
        }

        // 候補地から緯度、経度を返すようにする
        $destinations = $this->getLatLngFromDestinations($destinations);
        return response()->json($destinations);
    }

    #候補地が2追加だったときに、openapiを使って新しい候補地を取得する
    /**
     * @param string $region
     * @return string[] $generateDestinations
     */
    private function generateDestinationsFromRegion($region): array
    {
        // message内容
        $messages = [
            [
               'role' => 'user',
                'content' => "指定された地域: {$region} で一日で回れる人気の観光地を3つ提案してください。
                レスポンスは、以下のような形で返却してください。
                例（{$region}が東京だった場合）:
                \"東京タワー\",\"浅草寺\",\"東京ディズニーランド\""
            ]
        ];

        // OpenAIにリクエストを送信
        $result = OpenAI::chat()->create([
            'model' => 'gpt-4',
            'messages' => $messages,
        ]);

        // OpenAIの返答から候補地を抽出
        $generateDestinations = $result->choices[0]->message->content;

        if ($generateDestinations === null) {
            return [];
        }
        //レスポンスを整形してdestinationsの配列に直す
        $generateDestinations = explode(',', $generateDestinations);

        return $generateDestinations;
    }

    #候補地から緯度経度を返す処理
    /**
     * @param array<string> $destinations
     * @return array<array{name: string, lat: float, lng: float}>
     */
    private function getLatLngFromDestinations($destinations): array
    {
        $destinationsWithLatLng = [];

        //候補地からlat,lngをgeochiding apiで取得する
        foreach ($destinations as $destination) {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $destination,
                'key' => env('GOOGLE_MAP_API_KEY'),
            ]);

            //results->geometry->locationでlat,lngが取得できる
            /** @var array<string, mixed> $result */
            $result = $response->json();

            if (isset($result['status']) && $result['status'] === 'OK') {
                if (isset($result['results']) && is_array($result['results']) &&
                    isset($result['results'][0]['geometry']['location']) &&
                    is_array($result['results'][0]['geometry']['location'])) {
                    /** @var array<string, float> $location */
                    $location = $result['results'][0]['geometry']['location'];
                    $destinationsWithLatLng[] = [
                        'name' => $destination,
                        'lat' => $location['lat'],
                        'lng' => $location['lng'],
                    ];
                }
            }
        }
        return $destinationsWithLatLng;
    }
}

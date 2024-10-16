<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;

class TravelController extends Controller
{
    public function generateTravelPlan(Request $request)
    {
        // バリデーション
        $request->validate([
            'region' => 'required|string|max:255',
            'destinations' => 'array|nullable',
            'destinations.*' => 'string|max:255',
        ]);

        $region = $request->input('region');
        $destinations = $request->input('destinations', []);

        // 候補地がない場合、自動で地域から一日で回れる地点を選定
        if (empty($destinations)) {
            $destinations = $this->generateDestinationsFromRegion($region);
            if (is_array($destinations) && empty($destinations)) {
                return response()->json(['error' => '候補地の生成に失敗しました。'], 500);
            }
        }

        // 各地点の緯度と経度を取得
        $destinationsWithCoordinates = $this->getCoordinatesForDestinations($destinations);

        if (is_string($destinationsWithCoordinates)) {
            // エラーメッセージが返された場合
            return response()->json(['error' => $destinationsWithCoordinates], 500);
        }

        return response()->json([
            'destinations' => $destinationsWithCoordinates,
        ]);
    }

    private function generateDestinationsFromRegion($region)
    {
        // Chat API 用のメッセージ形式に変更
        $messages = [
            [
                'role' => 'system',
                'content' => 'あなたは旅行プランナーです。指定された地域で一日で回れる人気の観光地を3つ提案してください。',
            ],
            [
                'role' => 'user',
                'content' => "地域: {$region}",
            ],
        ];

        try {
            $result = openAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => $messages,
                'temperature' => 0.7,
            ]);

            $suggestionsText = $result->choices[0]->message->content;

            // 結果をパース（例: 数字リスト形式で提案されることを想定）
            $suggestions = explode("\n", trim($suggestionsText));
            $destinations = [];
            foreach ($suggestions as $suggestion) {
                if (preg_match('/^\d+\.\s*(.+)$/', $suggestion, $matches)) {
                    $destinations[] = trim($matches[1]);
                }
            }

            // 正常に3つの観光地が提案されなかった場合のフォールバック
            if (count($destinations) < 3) {
                // カンマ区切りで分割
                $destinations = preg_split('/,|\n/', $suggestionsText);
                $destinations = array_map('trim', $destinations);
                $destinations = array_filter($destinations);
                $destinations = array_slice($destinations, 0, 3);
            }

            return array_values($destinations);
        } catch (\Exception $e) {
            // エラーハンドリング
            return [];
        }
    }

    private function getCoordinatesForDestinations(array $destinations)
    {
        $destinationsWithCoordinates = [];

        foreach ($destinations as $destination) {
            $coordinates = $this->geocodeDestination($destination);
            if ($coordinates === null) {
                // 一つでもジオコーディングに失敗した場合、エラーメッセージを返す
                return "地点「{$destination}」の座標取得に失敗しました。";
            }
            $destinationsWithCoordinates[] = [
                'name' => $destination,
                'lat' => $coordinates['lat'],
                'lng' => $coordinates['lng'],
            ];
        }

        return $destinationsWithCoordinates;
    }

    private function geocodeDestination($destination)
    {
        // Google Maps Geocoding API を使用
        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $destination,
            'key' => env('GOOGLE_MAPS_API_KEY'),
        ]);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        if ($data['status'] !== 'OK' || empty($data['results'])) {
            return null;
        }

        $location = $data['results'][0]['geometry']['location'];
        return [
            'lat' => $location['lat'],
            'lng' => $location['lng'],
        ];
    }
}



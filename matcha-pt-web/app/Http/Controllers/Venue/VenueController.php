<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Services\MatchaDummyDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VenueController extends Controller
{
    public function index()
    {
        $dbVenues = Venue::with('courts')->latest()->get();
        if ($dbVenues->isNotEmpty()) {
            $venues = $dbVenues->map(function ($v) {
                return [
                    'id' => $v->venue_id,
                    'name' => $v->nama_venue,
                    'sport' => $v->courts->first()->sport->nama_sport ?? 'Padel',
                    'address' => $v->alamat,
                    'city' => 'Jakarta',
                    'pic_name' => $v->owner->nama ?? 'PIC Venue',
                    'pic_phone' => $v->owner->no_hp ?? '-',
                    'operating_hours' => '07:00 - 22:00',
                    'image' => $v->foto ?? 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80',
                    'facilities' => explode(',', $v->fasilitas ?? 'WC, Kantin, Parkir'),
                    'description' => $v->alamat,
                    'courts' => $v->courts,
                ];
            })->toArray();
        } else {
            $venues = MatchaDummyDataService::getVenues();
        }

        return view('venues.index', compact('venues'));
    }

    public function show($id)
    {
        $dbVenue = Venue::with('courts')->find((int) $id);
        if ($dbVenue) {
            $venue = [
                'id' => $dbVenue->venue_id,
                'name' => $dbVenue->nama_venue,
                'sport' => $dbVenue->courts->first()->sport->nama_sport ?? 'Padel',
                'address' => $dbVenue->alamat,
                'city' => 'Jakarta',
                'pic_name' => $dbVenue->owner->nama ?? 'PIC Venue',
                'pic_phone' => $dbVenue->owner->no_hp ?? '-',
                'operating_hours' => '07:00 - 22:00',
                'image' => $dbVenue->foto ?? 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80',
                'facilities' => explode(',', $dbVenue->fasilitas ?? 'WC, Kantin, Parkir'),
                'description' => $dbVenue->alamat,
                'courts' => $dbVenue->courts,
            ];
        } else {
            $venues = MatchaDummyDataService::getVenues();
            $venue = collect($venues)->firstWhere('id', (int) $id) ?? $venues[0];
        }

        return view('venues.show', compact('venue'));
    }

    public function create()
    {
        if (Auth::user()->role !== 'venue_owner') {
            return redirect()->route('venues.index')->with('error', 'Akses ditolak: Fitur pendaftaran venue khusus untuk Pemilik Venue.');
        }

        return view('venues.create');
    }
}

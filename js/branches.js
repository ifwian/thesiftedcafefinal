// branches.js - the ONE place that holds the branch details used by the Visit us map.
// To finish the map: replace each  lat / lng  below (they are null on purpose, because
// exact coordinates were not provided and must never be guessed).
//   How to get them: open Google Maps, right-click the exact spot of the cafe, click the
//   numbers shown at the top of the menu (for example 14.2000, 121.1600) to copy them.
// While lat/lng are null, the map shows the branch at its street address (Google Maps pin).
// Once BOTH branches have lat and lng, the page shows one interactive map with two pins.
window.SIFTED_BRANCHES = [
  {
    id: 'calamba',
    name: 'Calamba',
    address: 'Unit 2, Pabs2men Building, Chipeco Ave, Brgy. 3, Calamba, 4027 Laguna, Philippines',
    hours: 'Every day, 10:00 AM – 10:00 PM',
    phone: '0916 390 7048',   // same number for every branch
    lat: null,            // <-- PLACEHOLDER: paste the real latitude
    lng: null             // <-- PLACEHOLDER: paste the real longitude
  },
  {
    id: 'cabuyao',
    name: 'Cabuyao',
    address: '2nd Floor, Southwalk Commercial Space, South Point, Pulo, Cabuyao, Laguna 4025',
    hours: 'Every day, 9:00 AM – 9:00 PM',
    phone: '0916 390 7048',
    lat: null,            // <-- PLACEHOLDER: paste the real latitude
    lng: null             // <-- PLACEHOLDER: paste the real longitude
  }
];

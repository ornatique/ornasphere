@php
    $googleMapsApiKey = config('services.google.maps_api_key');
@endphp

@push('scripts')
<script>
    (function () {
        const addressInput = document.getElementById(@json($addressFieldId ?? 'address'));
        const cityInput = document.getElementById(@json($cityFieldId ?? 'city'));
        const areaInput = document.getElementById('area');
        const landmarkInput = document.getElementById('landmark');
        const pincodeInput = document.getElementById(@json($pincodeFieldId ?? 'pincode'));

        if (!addressInput || !cityInput || !pincodeInput) {
            return;
        }

        function parseAddressComponents(components) {
            let city = '';
            let area = '';
            let landmark = '';
            let pincode = '';

            (components || []).forEach((component) => {
                const types = component.types || [];

                if (types.includes('postal_code')) {
                    pincode = component.long_name || pincode;
                }
                if (types.includes('locality')) {
                    city = component.long_name || city;
                }
                if (!city && types.includes('postal_town')) {
                    city = component.long_name || city;
                }
                if (!city && types.includes('administrative_area_level_2')) {
                    city = component.long_name || city;
                }
                if (types.includes('sublocality') || types.includes('sublocality_level_1') || types.includes('neighborhood')) {
                    area = component.long_name || area;
                }
                if (types.includes('point_of_interest') || types.includes('premise') || types.includes('establishment')) {
                    landmark = component.long_name || landmark;
                }
            });

            return { city, area, landmark, pincode };
        }

        window.initCustomerAddressAutocomplete = function () {
            if (!window.google || !google.maps || !google.maps.places) {
                return;
            }

            const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                types: ['geocode'],
                fields: ['address_components', 'formatted_address'],
                componentRestrictions: { country: 'in' },
            });

            // City suggestions stay city-only; the Address field keeps its existing
            // full-address autocomplete below.
            const cityAutocomplete = new google.maps.places.Autocomplete(cityInput, {
                types: ['(cities)'],
                fields: ['address_components', 'name'],
                componentRestrictions: { country: 'in' },
            });
            const geocoder = new google.maps.Geocoder();
            let cityLookupSequence = 0;

            function lookupCityPostcode(cityName) {
                const sequence = ++cityLookupSequence;
                const city = (cityName || '').trim();
                if (!city) return;

                geocoder.geocode({ address: city + ', India' }, function (results, status) {
                    if (sequence !== cityLookupSequence || status !== 'OK' || !results) return;

                    // A city can contain many postcodes. Use a postcode only when
                    // Google returns one for the selected city/geocoding result.
                    for (const result of results) {
                        const parsed = parseAddressComponents(result.address_components || []);
                        if (parsed.pincode) {
                            pincodeInput.value = parsed.pincode;
                            return;
                        }
                    }
                });
            }

            cityInput.addEventListener('input', function () {
                cityLookupSequence++;
                pincodeInput.value = '';
            });

            cityAutocomplete.addListener('place_changed', function () {
                const place = cityAutocomplete.getPlace();
                if (!place) return;

                const parsed = parseAddressComponents(place.address_components || []);
                cityInput.value = parsed.city || place.name || '';
                pincodeInput.value = parsed.pincode || '';
                if (!parsed.pincode) lookupCityPostcode(cityInput.value);
            });

            cityInput.addEventListener('blur', function () {
                if (!pincodeInput.value.trim()) lookupCityPostcode(cityInput.value);
            });

            autocomplete.addListener('place_changed', function () {
                const place = autocomplete.getPlace();
                if (!place) return;

                if (place.formatted_address) {
                    addressInput.value = place.formatted_address;
                }

                const parsed = parseAddressComponents(place.address_components || []);
                if (parsed.city) cityInput.value = parsed.city;
                if (parsed.area && areaInput) areaInput.value = parsed.area;
                if (parsed.landmark && landmarkInput) landmarkInput.value = parsed.landmark;
                if (parsed.pincode) pincodeInput.value = parsed.pincode;
            });

            pincodeInput.addEventListener('blur', function () {
                const pin = (pincodeInput.value || '').trim();
                if (pin.length < 4) return;

                geocoder.geocode({ address: pin + ', India' }, function (results, status) {
                    if (status !== 'OK' || !results || !results.length) return;
                    const parsed = parseAddressComponents(results[0].address_components || []);
                    if (parsed.city) cityInput.value = parsed.city;
                    if (parsed.area && areaInput && !areaInput.value) areaInput.value = parsed.area;
                });
            });
        };
    })();
</script>

@if(!empty($googleMapsApiKey))
    <script async defer src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&libraries=places&callback=initCustomerAddressAutocomplete"></script>
@else
    <script>
        console.warn('Google Maps API key missing. Set GOOGLE_MAPS_API_KEY in .env to enable customer address auto-fill.');
    </script>
@endif
@endpush


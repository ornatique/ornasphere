@php
    $googleMapsApiKey = config('services.google.maps_api_key');
@endphp

@push('scripts')
<script>
    (function () {
        function cityFromComponents(components) {
            const preferredTypes = ['locality', 'postal_town', 'administrative_area_level_2'];
            for (const type of preferredTypes) {
                const component = (components || []).find((item) => (item.types || []).includes(type));
                if (component && component.long_name) return component.long_name;
            }
            return '';
        }

        window.initUserCityAutocomplete = function () {
            const cityInput = document.getElementById('user-city');
            const pincodeInput = document.getElementById('user-pincode');
            if (!cityInput || !pincodeInput || !window.google?.maps?.places) return;

            const autocomplete = new google.maps.places.Autocomplete(cityInput, {
                types: ['(cities)'],
                fields: ['address_components', 'name'],
                componentRestrictions: { country: 'in' },
            });

            autocomplete.addListener('place_changed', function () {
                const place = autocomplete.getPlace();
                if (!place) return;

                // Keep the field city-only; never copy Google's full formatted address here.
                cityInput.value = cityFromComponents(place.address_components) || place.name || '';
                const postalCode = (place.address_components || []).find((component) =>
                    (component.types || []).includes('postal_code')
                );

                // Postcodes are not unique per city. Clear any old postcode if Google
                // did not return one for the selected city result instead of guessing.
                pincodeInput.value = postalCode ? postalCode.long_name : '';
            });
        };
    })();
</script>

@if(!empty($googleMapsApiKey))
    <script async defer src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&libraries=places&callback=initUserCityAutocomplete"></script>
@else
    <script>
        console.warn('Google Maps API key missing. Set GOOGLE_MAPS_API_KEY to enable city autocomplete.');
    </script>
@endif
@endpush

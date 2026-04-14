@php
    $googleMapsApiKey = config('services.google.maps_api_key');
@endphp

@push('scripts')
<script>
    (function () {
        const addressInput = document.getElementById('address_1');
        const cityInput = document.getElementById('city');
        const stateInput = document.getElementById('state');
        const postcodeInput = document.getElementById('postcode');
        const countrySelect = document.getElementById('country');

        if (!addressInput || !cityInput || !stateInput || !postcodeInput || !countrySelect) {
            return;
        }

        const countryToCode = {
            'India': 'IN',
            'America': 'US',
            'Italy': 'IT',
            'Russia': 'RU',
            'Britain': 'GB',
        };

        function getSelectedCountryLabel() {
            const opt = countrySelect.options[countrySelect.selectedIndex];
            return (opt && opt.text ? opt.text : countrySelect.value || '').trim();
        }

        function setCountryByLabel(countryLongName) {
            if (!countryLongName) return;
            const target = countryLongName.trim().toLowerCase();
            Array.from(countrySelect.options).forEach((opt) => {
                if ((opt.text || '').trim().toLowerCase() === target) {
                    countrySelect.value = opt.value;
                }
            });
        }

        function parseAddressComponents(components) {
            let city = '';
            let state = '';
            let postcode = '';
            let country = '';

            (components || []).forEach((component) => {
                const types = component.types || [];

                if (types.includes('postal_code')) {
                    postcode = component.long_name || postcode;
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

                if (types.includes('administrative_area_level_1')) {
                    state = component.long_name || state;
                }

                if (types.includes('country')) {
                    country = component.long_name || country;
                }
            });

            return { city, state, postcode, country };
        }

        window.initCompanyAddressAutocomplete = function () {
            if (!window.google || !google.maps || !google.maps.places) {
                return;
            }

            const countryLabel = getSelectedCountryLabel();
            const countryCode = countryToCode[countryLabel];

            const options = {
                types: ['geocode'],
                fields: ['address_components', 'formatted_address'],
            };

            if (countryCode) {
                options.componentRestrictions = { country: countryCode };
            }

            const autocomplete = new google.maps.places.Autocomplete(addressInput, options);
            const geocoder = new google.maps.Geocoder();

            autocomplete.addListener('place_changed', function () {
                const place = autocomplete.getPlace();
                if (!place || !place.address_components) return;

                const parsed = parseAddressComponents(place.address_components);

                if (place.formatted_address) {
                    addressInput.value = place.formatted_address;
                }
                if (parsed.city) {
                    cityInput.value = parsed.city;
                }
                if (parsed.state) {
                    stateInput.value = parsed.state;
                }
                if (parsed.postcode) {
                    postcodeInput.value = parsed.postcode;
                }
                if (parsed.country) {
                    setCountryByLabel(parsed.country);
                }
            });

            function fillByPostcode() {
                const pin = (postcodeInput.value || '').trim();
                if (pin.length < 4) return;

                const country = getSelectedCountryLabel();
                const query = country ? `${pin}, ${country}` : pin;

                geocoder.geocode({ address: query }, function (results, status) {
                    if (status !== 'OK' || !results || !results.length) return;

                    const parsed = parseAddressComponents(results[0].address_components || []);
                    if (parsed.city) {
                        cityInput.value = parsed.city;
                    }
                    if (parsed.state) {
                        stateInput.value = parsed.state;
                    }
                    if (parsed.country) {
                        setCountryByLabel(parsed.country);
                    }
                });
            }

            postcodeInput.addEventListener('blur', fillByPostcode);
            countrySelect.addEventListener('change', function () {
                if ((postcodeInput.value || '').trim() !== '') {
                    fillByPostcode();
                }
            });
        };
    })();
</script>

@if(!empty($googleMapsApiKey))
    <script async defer src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&libraries=places&callback=initCompanyAddressAutocomplete"></script>
@else
    <script>
        console.warn('Google Maps API key missing. Set GOOGLE_MAPS_API_KEY in .env to enable address auto-fill.');
    </script>
@endif
@endpush

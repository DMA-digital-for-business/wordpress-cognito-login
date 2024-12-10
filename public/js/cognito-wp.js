function updateUserMeta() {
    fetch('/wp-json/custom/v1/profile-user', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': profiling_object.nonce // Passa il nonce
        }
    })
        .then((response) => response.json())
        .then((result) => {
            if (result.success) {
                console.log('User meta updated successfully ');
                    const fromUrl = new URLSearchParams(window.location.search).get("from")
                    if (fromUrl) {
                        window.location.href = fromUrl;
                    } else {
                        console.log("Il parametro 'from' non è presente.");
                    }
                
            } else {
                console.error('Error updating user meta:', result.message);
            }
        })
        .catch((error) => {
            console.error('Error during request:', error);
        });
}
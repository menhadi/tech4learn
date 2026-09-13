import { useEffect, useRef, useState } from "react";

/** Coordinates are a proposal until the existing centre approval action is used. */
export function CentreLocation({
  latitude,
  longitude,
}: {
  latitude: number | null;
  longitude: number | null;
}) {
  const lat = useRef<HTMLInputElement>(null);
  const lng = useRef<HTMLInputElement>(null);
  const request = useRef(0);
  const [locating, setLocating] = useState(false);
  const [message, setMessage] = useState("");
  function cancelPending() {
    request.current++;
    setLocating(false);
    setMessage(
      "Location edited. Check the coordinates, then save for approval.",
    );
  }
  function locate() {
    const token = ++request.current;
    if (!navigator.geolocation) {
      setMessage(
        "Location is unavailable on this device. Enter the coordinates manually.",
      );
      return;
    }
    setLocating(true);
    setMessage(
      "Getting your current location. Allow location access when your browser asks.",
    );
    const originalLat = lat.current?.value;
    const originalLng = lng.current?.value;
    navigator.geolocation.getCurrentPosition(
      (position) => {
        if (token !== request.current || !lat.current || !lng.current) return;
        setLocating(false);
        // A restored draft or manual correction wins over a late device response.
        if (
          lat.current.value !== originalLat ||
          lng.current.value !== originalLng
        )
          return;
        lat.current.value = position.coords.latitude.toFixed(7);
        lng.current.value = position.coords.longitude.toFixed(7);
        lat.current.dispatchEvent(new Event("input", { bubbles: true }));
        lng.current.dispatchEvent(new Event("input", { bubbles: true }));
        setMessage(
          `Location captured (accuracy approximately ${Math.round(position.coords.accuracy)} metres). Check it, then save for approval.`,
        );
      },
      (error) => {
        if (token !== request.current) return;
        setLocating(false);
        setMessage(
          error.code === 1
            ? "Location access was denied. Allow it in your browser and retry, or enter coordinates manually."
            : "Could not get your location. Retry at the centre or enter coordinates manually.",
        );
      },
      { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 },
    );
  }
  useEffect(() => {
    if (latitude === null && longitude === null) locate();
    return () => {
      request.current++;
    };
  }, []);
  return (
    <section aria-label="Centre location">
      <h4>Centre location</h4>
      <p>
        Capture this while you are at the centre. Check the detected coordinates
        and correct them if needed. An authorised person approves the saved
        location.
      </p>
      <button
        type="button"
        className="secondary"
        disabled={locating}
        onClick={locate}
      >
        {locating ? "Getting location..." : "Use my current location"}
      </button>
      <p role="status">
        {message ||
          "Saved coordinates are shown below. Capture again only when you want to replace them."}
      </p>
      <div className="form-grid">
        <label>
          Latitude
          <input
            ref={lat}
            name="latitude"
            type="number"
            step="any"
            min={-90}
            max={90}
            defaultValue={latitude ?? ""}
            onChange={cancelPending}
          />
        </label>
        <label>
          Longitude
          <input
            ref={lng}
            name="longitude"
            type="number"
            step="any"
            min={-180}
            max={180}
            defaultValue={longitude ?? ""}
            onChange={cancelPending}
          />
        </label>
      </div>
    </section>
  );
}

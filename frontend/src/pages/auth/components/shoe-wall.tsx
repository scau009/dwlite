export function ShoeWall() {
  return (
    <div className="shoe-wall-container">
      {/* Animated gradient background */}
      <div className="shoe-wall-gradient" />

      {/* Decorative circles */}
      <div className="shoe-wall-circle shoe-wall-circle-1" />
      <div className="shoe-wall-circle shoe-wall-circle-2" />
      <div className="shoe-wall-circle shoe-wall-circle-3" />

      {/* Tagline */}
      <div className="shoe-wall-tagline">
        <h2>Where Sneakers</h2>
        <h2>Meet Business</h2>
        <p>Your B2B sneaker supply chain platform</p>
      </div>
    </div>
  );
}

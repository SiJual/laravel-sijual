"""SiPasar — Domain models (internal data classes, not exposed via API)."""

from dataclasses import dataclass, field


@dataclass
class CompetitorItem:
    """A single competitor POI."""

    name: str
    category: str
    latitude: float
    longitude: float
    rating: float | None
    review_count: int
    distance_meters: float
    place_id: str = ""
    address: str = ""
    source: str = ""
    maps_uri: str = ""


@dataclass
class CompetitorAnalysisResult:
    """Output from competitor_service."""

    count: int
    avg_rating: float
    competition_score: float  # 0.0 – 1.0
    competition_level: str  # "rendah" | "sedang" | "tinggi"
    competitors: list[CompetitorItem] = field(default_factory=list)
    competition_density_per_km2: float = 0.0
    avg_distance_meters: float = 0.0
    data_source: str = ""
    provider_status: str = "ok"


@dataclass
class GeodemografiResult:
    """Output from geodemografi_service."""

    population_estimate: int
    population_density_per_km2: float
    economic_indicator: str  # "rendah" | "menengah" | "tinggi"
    dominant_consumer_segment: str  # e.g. "pekerja_kantoran", "permukiman", "pelajar"
    area_name: str = ""  # kecamatan/kelurahan name
    purchasing_power_proxy: float = 0.5  # 0.0 – 1.0 normalised


@dataclass
class ScoringResult:
    """Output from scoring_service."""

    score: float  # 0.0 – 1.0
    label: str  # "tinggi" | "sedang" | "rendah"
    narrative: str  # Human-readable explanation
    demand_proxy: float = 0.0
    purchasing_power_proxy: float = 0.0
    competition_factor: float = 0.0
    category_fit: float = 0.0

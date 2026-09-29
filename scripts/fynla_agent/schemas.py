"""Pydantic output schemas for Fynla Agent structured responses."""
from pydantic import BaseModel, Field


class Recommendation(BaseModel):
    title: str = Field(description="Short title of the recommendation")
    module: str = Field(description="Financial module: protection, savings, investment, retirement, estate, goals, tax")
    urgency: str = Field(description="Urgency level: high, medium, low")
    rationale: str = Field(description="Why this recommendation matters")


class ActionStep(BaseModel):
    step: int = Field(description="Step number in the action plan")
    action: str = Field(description="Description of the action to take")
    timeframe: str = Field(description="Suggested timeframe, e.g. 'within 1 month'")


class HolisticPlanOutput(BaseModel):
    executive_summary: str = Field(description="High-level summary of the user's financial position")
    ranked_recommendations: list[Recommendation] = Field(default_factory=list, description="Prioritised list of recommendations")
    action_plan: list[ActionStep] = Field(default_factory=list, description="Step-by-step action plan")
    conflicts: list[str] = Field(default_factory=list, description="Cross-module conflicts identified")
    strategies: list[str] = Field(default_factory=list, description="Cross-module strategies recommended")


class ScenarioOutput(BaseModel):
    current_state: dict = Field(default_factory=dict, description="Snapshot of current financial position")
    projected_state: dict = Field(default_factory=dict, description="Projected position after the scenario change")
    impact_analysis: str = Field(description="Narrative analysis of the scenario impact")
    feasibility: str = Field(description="Assessment of whether the scenario is feasible")


class DeepRecommendation(BaseModel):
    title: str = Field(description="Recommendation title")
    module: str = Field(description="Financial module this applies to")
    rationale: str = Field(description="Detailed reasoning")
    cost_benefit: str = Field(description="Cost-benefit analysis summary")
    decision_trace: str = Field(description="Chain of reasoning that led to this recommendation")


class DeepRecommendationOutput(BaseModel):
    recommendations: list[DeepRecommendation] = Field(default_factory=list, description="Deep recommendations with full reasoning traces")

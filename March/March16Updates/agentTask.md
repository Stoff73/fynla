# Agent System Upgrade - Task Tracker

## Status: In Progress
**Branch:** `aiUpgrade`
**Started:** 2026-03-16

---

## Phase 0: Branch Setup
- [x] **0.1** Create and checkout `aiUpgrade` branch

## Phase 1: Foundation & SDK Installation
- [ ] **1.1** Install Anthropic PHP SDK: `composer require anthropic-ai/sdk`
- [ ] **1.2** Register `\Anthropic\Client` singleton in `AppServiceProvider`
- [ ] **1.3** Update `config/services.php` with `advanced_chat_model` key
- [ ] **1.4** Verify PHP SDK works: smoke test
- [ ] **1.5** Create `scripts/fynla_agent/` directory structure
- [ ] **1.6** Create `scripts/requirements.txt`
- [ ] **1.7** Install Python dependencies
- [ ] **1.8** Verify Python Agent SDK works
- [ ] **1.9** Add `AGENT_INTERNAL_TOKEN` to `.env` and `.env.example`
- [ ] **1.10** Run tests - baseline passes

## Phase 2: Prerequisite Gate System
- [ ] **2.1** Create `PrerequisiteGateService.php` with module gate methods
- [ ] **2.2-2.8** Define prerequisite rules for all 7 modules
- [ ] **2.9** Define holistic plan gate
- [ ] **2.10** Define tool execution gates
- [ ] **2.11** Define advice gates
- [ ] **2.12** Integrate with existing DataReadinessServices
- [ ] **2.13** Add `getDecisionTreePrerequisites()`
- [ ] **2.14** Write unit tests
- [ ] **2.15** Run tests - all pass

## Phase 3: Guardrails Trait
- [ ] **3.1-3.7** Create `HasAiGuardrails.php` with model selection, token budgets, error handling
- [ ] **3.8** Run tests - all pass

## Phase 4: Chat Trait
- [ ] **4.1-4.12** Create `HasAiChat.php` with streaming, prompt building, message persistence
- [ ] **4.13-4.14** Write tests, all pass

## Phase 5: Tool Execution Migration
- [ ] **5.1-5.12** Migrate all tool execution to CoordinatingAgent with prerequisite gates
- [ ] **5.13** Run tests - all pass

## Phase 6: Wire Up Agent Prerequisite Gates
- [ ] **6.1-6.10** Add prerequisite gate checks to all 7 module agents + integration tests
- [ ] **6.11** Run tests - all pass

## Phase 7: Python Agent SDK Sidecar
- [ ] **7.1-7.6** Create Python scripts (config, schemas, tools, hooks, agent, runner)
- [ ] **7.7-7.10** Create PHP bridge, middleware, controller, routes
- [ ] **7.11-7.12** Wire into CoordinatingAgent
- [ ] **7.13-7.14** Test end-to-end, all tests pass

## Phase 8: Controller Update & Cleanup
- [ ] **8.1-8.2** Update AiChatController
- [ ] **8.3-8.9** Delete 7 old AI service files
- [ ] **8.10-8.11** Run tests, format code

## Phase 9: End-to-End Testing
- [ ] **9.1-9.17** Full manual testing matrix

## Phase 10: Documentation & Deployment Prep
- [ ] **10.1-10.5** Documentation updates, deployment prep

# Starting food-safety controls

Checked against primary UK sources on 22 October 2026. These controls are STARTING HOUSE STANDARDS, not a complete HACCP plan or regulatory approval. The operator must validate them for the actual food, process, location and supplier instructions before live use. The app deliberately flags problems rather than automatically authorising time exceptions or declaring food safe.

| Control | Starting rule | Basis / qualification |
| --- | --- | --- |
| Fridge / chilled delivery | Operating maximum 5°C; critical above 8°C | GOV.UK recommends a fridge setting of 5°C or colder. Chilled food generally has an 8°C ceiling in England, Wales and Northern Ireland, with exceptions and product-specific conditions. A display/air reading is not necessarily the actual food temperature. |
| Freezer / frozen delivery | -18°C or colder | Example operating target; validate against frozen-product specifications and the kitchen plan. A failed operating target does not by itself establish the food's safety or legal status. |
| Hot holding | At least 63°C | GOV.UK business guidance. Below-limit readings are always flagged. The app does not assume a permitted time exception when the time below limit is unknown. |
| Cooking / reheating | At least 75°C held continuously for 30 seconds | House standard using an accepted cooking combination. Equivalent validated combinations exist. Reheat only once. Scotland has specific reheating rules; check CookSafe and set the appropriate validated standard. |
| Cooling rice | At most 8°C within 60 minutes | Configurable numeric HOUSE RULE. FSA advises cooling rice quickly, ideally within one hour, followed by refrigeration. The specific combined 8°C/60-minute target is not presented as a universal statutory rule. Keep refrigerated cooked rice for no more than 24 hours; reheat only once. |
| Cooling other food | At most 8°C within 90 minutes | Example HOUSE RULE requiring process validation. This is not a universal UK legal limit. Use an appropriate rapid-cooling method and validate the batch size and actual chilling capacity. |
| Probe ice check | -1°C to +1°C | Example house tolerance based on SFBB probe checks; prepare the ice mixture correctly and follow manufacturer guidance. |
| Probe boiling check | 99°C to 101°C | Example house tolerance at normal atmospheric pressure. Adjust for local boiling point and instrument instructions. |

A green result means only that the entered values meet the captured house limits. It does not establish food safety, validate the measurement, replace sensory/product checks or certify HACCP compliance. Missing checks and false readings cannot be repaired by software.

## Official sources

- GOV.UK, Cooking food safely: https://www.gov.uk/food-hygiene-businesses/cooking
- GOV.UK, Chilling and freezing: https://www.gov.uk/food-hygiene-businesses/chilling-and-freezing
- GOV.UK, Food safety management systems: https://www.gov.uk/food-safety-management-systems
- Food Standards Agency, Home food fact checker (rice): https://www.food.gov.uk/safety-hygiene/home-food-fact-checker
- Food Standards Agency, Safer food, better business: https://www.food.gov.uk/business-guidance/safer-food-better-business-sfbb
- Food Standards Scotland, CookSafe: https://www.foodstandards.gov.scot/business-guidance/running-a-food-business/publications/cooksafe-guide

The user-supplied https://food-safety.app/ was reviewed as a feature reference. This implementation has its own PHP/MySQL architecture and original interface. It is not affiliated with that service. Features of that service are not implied to exist here unless described in this release.

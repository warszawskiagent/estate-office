<?php
/**
 * Trait z logiką przypisywania agentów w panelu admina.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Trait Estate_Office_Admin_Agent_Assignment
 */
trait Estate_Office_Admin_Agent_Assignment {

    /**
     * Repozytorium agentów.
     *
     * @var Estate_Office_Agent_Repository
     */
    protected Estate_Office_Agent_Repository $agent_repository;

    /**
     * Bufor etykiet agentów.
     *
     * @var array<int,string>
     */
    protected array $agent_labels_cache = [];

    /**
     * ID agenta powiązanego z aktualnym użytkownikiem.
     *
     * @var int|null
     */
    protected ?int $current_user_agent_id = null;

    /**
     * Inicjuje repozytorium agentów.
     *
     * @param Estate_Office_Agent_Repository|null $agent_repository Repozytorium.
     *
     * @return void
     */
    protected function init_agent_repository( ?Estate_Office_Agent_Repository $agent_repository = null ) : void {
        $this->agent_repository = $agent_repository ?? new Estate_Office_Agent_Repository();
    }

    /**
     * Zwraca listę dostępnych agentów dla pola wyboru.
     *
     * @return array<string,string>
     */
    protected function get_agent_select_options() : array {
        $options = [ '0' => __( 'Brak opiekuna', 'estate-office' ) ];

        if ( $this->can_assign_all_agents() ) {
            foreach ( $this->agent_repository->get_dropdown_options() as $id => $label ) {
                $options[ (string) $id ]           = $label;
                $this->agent_labels_cache[ (int) $id ] = $label;
            }

            return $options;
        }

        $current_agent = $this->get_current_user_agent_id();
        if ( $current_agent > 0 ) {
            $this->prime_agent_labels( [ $current_agent ] );
            if ( isset( $this->agent_labels_cache[ $current_agent ] ) ) {
                $options[ (string) $current_agent ] = $this->agent_labels_cache[ $current_agent ];
            }
        }

        return $options;
    }

    /**
     * Czy bieżący użytkownik może przypisywać dowolnych agentów.
     *
     * @return bool
     */
    protected function can_assign_all_agents() : bool {
        return current_user_can( 'manage_estate_office_agents' );
    }

    /**
     * Normalizuje wybrany identyfikator agenta.
     *
     * @param int $agent_id ID agenta.
     *
     * @return int
     */
    protected function sanitize_agent_selection( int $agent_id ) : int {
        if ( $this->can_assign_all_agents() ) {
            return $this->agent_repository->exists( $agent_id ) ? $agent_id : 0;
        }

        $current_agent = $this->get_current_user_agent_id();

        return $current_agent > 0 ? $current_agent : 0;
    }

    /**
     * Zwraca ID agenta powiązanego z bieżącym użytkownikiem.
     *
     * @return int
     */
    protected function get_current_user_agent_id() : int {
        if ( null === $this->current_user_agent_id ) {
            $agent = $this->agent_repository->find_by_user_id( get_current_user_id() );
            $this->current_user_agent_id = $agent ? (int) $agent->id : 0;
        }

        if ( $this->current_user_agent_id > 0 ) {
            $this->prime_agent_labels( [ $this->current_user_agent_id ] );
        }

        return (int) $this->current_user_agent_id;
    }

    /**
     * Ładuje etykiety agentów do bufora.
     *
     * @param array<int,int> $agent_ids Lista identyfikatorów agentów.
     *
     * @return void
     */
    protected function prime_agent_labels( array $agent_ids ) : void {
        $missing = [];

        foreach ( $agent_ids as $agent_id ) {
            $agent_id = (int) $agent_id;
            if ( $agent_id <= 0 ) {
                continue;
            }

            if ( ! isset( $this->agent_labels_cache[ $agent_id ] ) ) {
                $missing[] = $agent_id;
            }
        }

        if ( empty( $missing ) ) {
            return;
        }

        $labels = $this->agent_repository->get_display_names_for_ids( $missing );

        foreach ( $labels as $id => $label ) {
            $this->agent_labels_cache[ (int) $id ] = $label;
        }
    }

    /**
     * Zwraca etykietę agenta.
     *
     * @param int $agent_id ID agenta.
     *
     * @return string
     */
    protected function get_agent_label( int $agent_id ) : string {
        if ( $agent_id <= 0 ) {
            return __( 'Brak opiekuna', 'estate-office' );
        }

        $this->prime_agent_labels( [ $agent_id ] );

        return $this->agent_labels_cache[ $agent_id ] ?? __( 'Brak opiekuna', 'estate-office' );
    }

    /**
     * Formatuje zawartość kolumny opiekuna.
     *
     * @param int $agent_id ID agenta.
     *
     * @return string
     */
    protected function format_agent_cell( int $agent_id ) : string {
        if ( $agent_id <= 0 ) {
            return esc_html__( 'Brak opiekuna', 'estate-office' );
        }

        $label = $this->get_agent_label( $agent_id );

        if ( current_user_can( 'manage_estate_office_agents' ) ) {
            $url = add_query_arg(
                [
                    'page'     => 'estate-office-agents',
                    'action'   => 'edit',
                    'agent_id' => $agent_id,
                ],
                admin_url( 'admin.php' )
            );

            return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
        }

        return esc_html( $label );
    }
}


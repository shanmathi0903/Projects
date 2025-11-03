package beanFolder;

import java.util.ArrayList;
import java.util.HashMap;

public class Bus{
	private int busId;
	private String busName;
	private String regNo;
	private String source;
	private String destination;
	private ArrayList<String> route=new ArrayList<>();
	private ArrayList<Float> price=new ArrayList<>();
	private int seater;
	private int sleeper;
	private int availableSeater;
	private int availableSleeper;
	private float sleeperPrice;
	private HashMap<String,ArrayList<Integer>> seaterSeats=new HashMap<>();
    private HashMap<String,ArrayList<Integer>> sleeperSeats=new HashMap<>();

	private boolean hasAc;
	
	public Bus(int busId,String busName,String regNo,String source,String destination,ArrayList<String> route,ArrayList<Float> price,int seater,int sleeper,float sleeperPrice,boolean hasAc) {
		this.busId=busId;
		this.busName=busName;
		this.regNo=regNo;
		this.source=source;
		this.destination=destination;
		this.route=route;
		this.price=price;
		this.seater=seater;
		this.sleeper=sleeper;
		this.availableSeater=seater;
		this.availableSleeper=sleeper;
		this.sleeperPrice=sleeperPrice;
		this.hasAc=hasAc;
	}
	
	public int getBusId(){
		return busId;
	}
	public String getBusName(){
		return busName;
	}
	public String getRegNo(){
		return regNo;
	}
	public String getSource(){
		return source;
	}
	public String getDestination(){
		return destination;
	}
	
	public ArrayList<String> getRoute(){
		return route;
	}
	public ArrayList<Float> getPrice(){
		return price;
	}
	
	public int getSeater(){
		return seater;
	}
	public int getSleeper(){
		return sleeper;
	}
	public int getAvailableSeater(){
		return availableSeater;
	}
	public int getAvailableSleeper(){
		return availableSleeper;
	}
	public void setAvailableSeater(int availableSeater){
        this.availableSeater=availableSeater;
    }
    public void setAvailableSleeper(int availableSleeper){
        this.availableSleeper=availableSleeper;
    }
	public float getSleeperPrice(){
		return sleeperPrice;
	}
	public boolean isHasAc(){
		return hasAc;
	}
	
	public HashMap<String,ArrayList<Integer>> getSeaterSeats(){
		return seaterSeats; 
	}
    public HashMap<String,ArrayList<Integer>> getSleeperSeats(){
		return sleeperSeats; 
	}

    public void setSeaterSeats(HashMap<String,ArrayList<Integer>> seaterSeats){
        this.seaterSeats=seaterSeats;
    }
    public void setSleeperSeats(HashMap<String,ArrayList<Integer>> sleeperSeats){
        this.sleeperSeats=sleeperSeats;
    }

}